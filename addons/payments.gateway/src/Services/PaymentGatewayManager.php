<?php

namespace Semizzy\Addons\Payments\Services;

use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentGatewayManager
{
    public function providersFor(string $capability): array
    {
        return PaymentGatewayProvider::query()
            ->where('enabled', true)
            ->where('paused', false)
            ->where('maintenance', false)
            ->where(function ($query) {
                $query->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<=', now());
            })
            ->get()
            ->filter(fn (PaymentGatewayProvider $provider) => $provider->supports($capability) && app(PaymentGatewayAdapterRegistry::class)->has($provider->driver))
            ->sortBy(fn (PaymentGatewayProvider $provider) => [$provider->priority, -$provider->weight])
            ->values()
            ->all();
    }

    public function adapter(PaymentGatewayProvider $provider): PaymentGatewayAdapter
    {
        return app(PaymentGatewayAdapterRegistry::class)->make($provider->driver);
    }

    public function execute(string $capability, callable $operation): array
    {
        $providers = $this->providersFor($capability);

        if (!$providers) {
            throw new RuntimeException("No enabled payment gateway supports [{$capability}].");
        }

        $last = null;

        foreach ($providers as $provider) {
            try {
                $result = $operation($provider);
                $provider->forceFill([
                    'failure_count' => 0,
                    'last_success_at' => now(),
                    'last_error' => null,
                ])->save();

                return $result;
            } catch (\Throwable $e) {
                $last = $e;
                $provider->increment('failure_count');
                $provider->forceFill([
                    'last_failure_at' => now(),
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                    'cooldown_until' => now()->addMinutes(min(30, max(1, $provider->failure_count))),
                ])->save();

                Log::warning('Payment gateway provider failed; trying next provider.', [
                    'provider' => $provider->code,
                    'capability' => $capability,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw $last ?: new RuntimeException('Payment gateway execution failed.');
    }
}