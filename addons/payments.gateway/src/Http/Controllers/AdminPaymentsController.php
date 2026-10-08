<?php

namespace Semizzy\Addons\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Semizzy\Addons\Payments\Models\PaymentIntent;
use Semizzy\Addons\Payments\Services\PaymentGatewayAdapterRegistry;
use Semizzy\Addons\Payments\Services\PaymentReconciliationService;

final class AdminPaymentsController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = PaymentIntent::query()->latest('id')->paginate(25)->through(fn (PaymentIntent $p): array => [
            'reference'=>$p->reference,'user_id'=>$p->user_id,'amount_minor'=>$p->amount_minor,'currency'=>$p->currency,
            'status'=>$p->status,'provider_reference'=>$p->provider_reference,'expires_at'=>$p->expires_at?->toISOString(),
            'paid_at'=>$p->paid_at?->toISOString(),'created_at'=>$p->created_at?->toISOString(),
        ]);

        $providers = PaymentGatewayProvider::query()->orderBy('priority')->orderBy('id')->get()->map(
            fn (PaymentGatewayProvider $provider): array => $this->providerPayload($provider)
        )->values()->all();

        return Inertia::render('Admin/Payments', [
            'payments' => $payments,
            'providers' => $providers,
            'available_drivers' => $this->availableDrivers(),
            'capabilities' => [
                'collect_payment','card_payment','bank_transfer_collection','virtual_account',
                'account_name_enquiry','single_payout','bulk_payout','webhook','requery','refund','settlement',
            ],
        ]);
    }

    public function storeProvider(Request $request): RedirectResponse
    {
        $data = $this->validateProvider($request, true);
        PaymentGatewayProvider::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'driver' => $data['driver'],
            'base_url' => $data['base_url'] ?: null,
            'credentials' => $data['credentials'],
            'capabilities' => $data['capabilities'],
            'priority' => $data['priority'],
            'weight' => $data['weight'],
            'enabled' => false,
            'paused' => true,
            'maintenance' => false,
            'settings' => $data['settings'],
        ]);

        return back()->with('success', 'Payment gateway provider created disabled. Test it before enabling.');
    }

    public function updateProvider(Request $request, int $provider): RedirectResponse
    {
        $model = PaymentGatewayProvider::query()->findOrFail($provider);
        $data = $this->validateProvider($request, false);

        $sensitiveChanged = false;
        foreach (['driver','base_url','capabilities','settings'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== $model->getAttribute($field)) {
                $sensitiveChanged = true;
            }
        }

        $model->forceFill([
            'name' => $data['name'],
            'code' => $data['code'],
            'driver' => $data['driver'],
            'base_url' => $data['base_url'] ?: null,
            'capabilities' => $data['capabilities'],
            'priority' => $data['priority'],
            'weight' => $data['weight'],
            'settings' => $data['settings'],
        ]);

        if ($data['credentials'] !== null) {
            $model->credentials = $data['credentials'];
            $sensitiveChanged = true;
        }

        if ($sensitiveChanged) {
            $model->forceFill([
                'enabled' => false,
                'paused' => true,
                'failure_count' => 0,
                'cooldown_until' => null,
                'last_error' => null,
            ]);
        }

        $model->saveOrFail();
        return back()->with('success', $sensitiveChanged
            ? 'Gateway updated and safely disabled. Run Test Connection before enabling it again.'
            : 'Gateway updated.');
    }

    public function testProvider(int $provider, PaymentGatewayAdapterRegistry $registry): JsonResponse|RedirectResponse
    {
        $model = PaymentGatewayProvider::query()->findOrFail($provider);

        try {
            if (!$registry->has($model->driver)) {
                throw new \RuntimeException('No registered adapter exists for this gateway driver.');
            }

            $ok = $registry->make($model->driver)->healthCheck($model);
            $model->forceFill([
                'last_health_check_at' => now(),
                'last_error' => $ok ? null : 'Gateway health check did not confirm availability.',
                'failure_count' => $ok ? 0 : $model->failure_count + 1,
                'last_failure_at' => $ok ? $model->last_failure_at : now(),
                'cooldown_until' => $ok ? null : now()->addMinutes(5),
            ])->saveOrFail();

            $payload = ['status' => $ok ? 'SUCCESS' : 'FAILED', 'message' => $ok ? 'Gateway health check succeeded.' : 'Gateway health check failed. Provider remains disabled.'];

            return request()->expectsJson()
                ? response()->json($payload, $ok ? 200 : 502)
                : back()->with($ok ? 'success' : 'error', $payload['message']);
        } catch (\Throwable $e) {
            Log::warning('Payment gateway health check failed.', [
                'provider_id' => $model->id,
                'driver' => $model->driver,
                'exception_class' => get_class($e),
            ]);
            $model->forceFill([
                'last_health_check_at' => now(),
                'last_failure_at' => now(),
                'failure_count' => $model->failure_count + 1,
                'cooldown_until' => now()->addMinutes(5),
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
                'enabled' => false,
                'paused' => true,
            ])->saveOrFail();

            return request()->expectsJson()
                ? response()->json(['status'=>'FAILED','message'=>'Gateway health check failed safely.'], 502)
                : back()->with('error', 'Gateway health check failed safely. Review the provider configuration.');
        }
    }

    public function requeryPayment(int $payment, PaymentReconciliationService $reconciliation): RedirectResponse
    {
        try {
            $reconciliation->requery(PaymentIntent::query()->findOrFail($payment));
            return back()->with('success', 'Provider requery completed.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Provider requery did not complete: '.$e->getMessage());
        }
    }

    public function setState(Request $request, int $provider): RedirectResponse
    {
        $data = $request->validate([
            'state' => ['required','in:enable,disable,pause,resume,maintenance_on,maintenance_off'],
        ]);
        $model = PaymentGatewayProvider::query()->findOrFail($provider);

        if ($data['state'] === 'enable') {
            if (!app(PaymentGatewayAdapterRegistry::class)->has($model->driver)) {
                return back()->with('error', 'Cannot enable: no registered server adapter exists for this driver.');
            }
            try {
                if (!app(PaymentGatewayAdapterRegistry::class)->make($model->driver)->healthCheck($model)) {
                    return back()->with('error', 'Cannot enable: provider health check failed.');
                }
            } catch (\Throwable) {
                return back()->with('error', 'Cannot enable: provider health check failed.');
            }
            $model->forceFill(['enabled'=>true,'paused'=>false,'maintenance'=>false,'cooldown_until'=>null,'failure_count'=>0,'last_error'=>null])->saveOrFail();
        } elseif ($data['state'] === 'disable') {
            $model->forceFill(['enabled'=>false])->saveOrFail();
        } elseif ($data['state'] === 'pause') {
            $model->forceFill(['paused'=>true])->saveOrFail();
        } elseif ($data['state'] === 'resume') {
            $model->forceFill(['paused'=>false,'maintenance'=>false])->saveOrFail();
        } elseif ($data['state'] === 'maintenance_on') {
            $model->forceFill(['maintenance'=>true,'paused'=>true])->saveOrFail();
        } elseif ($data['state'] === 'maintenance_off') {
            $model->forceFill(['maintenance'=>false])->saveOrFail();
        }

        return back()->with('success', 'Gateway state updated safely.');
    }

    private function providerPayload(PaymentGatewayProvider $provider): array
    {
        $credentials = $provider->credentials;
        $credentialKeys = array_values(array_filter(array_keys($credentials), fn ($key) => $key !== 'password'));
        return [
            'id'=>$provider->id,'name'=>$provider->name,'code'=>$provider->code,'driver'=>$provider->driver,
            'base_url'=>$provider->base_url,'capabilities'=>$provider->capabilities ?? [],
            'priority'=>$provider->priority,'weight'=>$provider->weight,'enabled'=>(bool)$provider->enabled,
            'paused'=>(bool)$provider->paused,'maintenance'=>(bool)$provider->maintenance,
            'failure_count'=>$provider->failure_count,'cooldown_until'=>$provider->cooldown_until?->toISOString(),
            'last_health_check_at'=>$provider->last_health_check_at?->toISOString(),
            'last_success_at'=>$provider->last_success_at?->toISOString(),
            'last_failure_at'=>$provider->last_failure_at?->toISOString(),
            'last_error'=>$provider->last_error,'credential_keys'=>$credentialKeys,
            'settings'=>$provider->settings ?? [],
            'webhook_url'=>url('/api/v1/payments/webhooks/'.$provider->code),
            'adapter_registered'=>app(PaymentGatewayAdapterRegistry::class)->has($provider->driver),
        ];
    }

    private function availableDrivers(): array
    {
        return ['paystack','opay','monnify','kora','squad','flutterwave','payaza'];
    }

    private function validateProvider(Request $request, bool $creating): array
    {
        $rules = [
            'name'=>['required','string','max:120'],
            'code'=>['required','string','max:100','regex:/^[A-Za-z0-9._-]+$/'],
            'driver'=>['required','string','max:100','regex:/^[A-Za-z0-9._-]+$/'],
            'base_url'=>['nullable','url','max:500'],
            'priority'=>['required','integer','min:0','max:100000'],
            'weight'=>['required','integer','min:1','max:100000'],
            'capabilities'=>['required','array','max:30'],
            'capabilities.*'=>['string','max:80'],
            'settings'=>['nullable','array'],
            'credentials'=>['nullable','array'],
        ];
        if ($creating) $rules['code'][] = 'unique:payment_gateway_providers,code';
        else $rules['code'][] = 'unique:payment_gateway_providers,code,'.$request->route('provider');

        $data = $request->validate($rules);
        if (!$creating && empty($data['credentials'])) $data['credentials'] = null;
        $data['base_url'] = $data['base_url'] ?? null;
        $data['settings'] = $data['settings'] ?? [];
        $data['credentials'] = $data['credentials'] ?? null;
        return $data;
    }
}
