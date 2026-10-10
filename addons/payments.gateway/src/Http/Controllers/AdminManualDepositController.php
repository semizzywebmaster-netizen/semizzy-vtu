<?php

namespace Semizzy\Addons\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Semizzy\Addons\Payments\Models\ManualDeposit;
use Semizzy\Addons\Payments\Models\ManualDepositMethod;
use Semizzy\Addons\Payments\Services\ManualDepositService;

final class AdminManualDepositController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ManualDeposits', [
            'methods' => ManualDepositMethod::query()->orderBy('priority')->orderBy('id')->get(),
            'deposits' => ManualDeposit::query()
                ->with(['user:id,name,email','method:id,name,bank_name,account_name,account_number'])
                ->latest('id')->paginate(30)
                ->through(fn (ManualDeposit $deposit): array => [
                    'id' => $deposit->id,
                    'reference' => $deposit->reference,
                    'user' => $deposit->user,
                    'method' => $deposit->method,
                    'amount_minor' => $deposit->amount_minor,
                    'currency' => $deposit->currency,
                    'status' => $deposit->status,
                    'proof_original_name' => $deposit->proof_original_name,
                    'user_note' => $deposit->user_note,
                    'admin_note' => $deposit->admin_note,
                    'submitted_at' => $deposit->submitted_at?->toISOString(),
                    'reviewed_at' => $deposit->reviewed_at?->toISOString(),
                    'proof_url' => route('admin.payments.manual-deposits.proof', $deposit->id),
                ]),
        ]);
    }

    public function storeMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'bank_name' => ['required','string','max:120'],
            'account_name' => ['required','string','max:160'],
            'account_number' => ['required','string','regex:/^\d{10,20}$/'],
            'instructions' => ['nullable','string','max:3000'],
            'priority' => ['required','integer','min:0','max:100000'],
        ]);

        ManualDepositMethod::create([
            ...$data,
            'currency' => 'NGN',
            'enabled' => false,
        ]);

        return back()->with('success','Manual deposit method created disabled. Enable it after verifying the account details.');
    }

    public function toggleMethod(int $method): RedirectResponse
    {
        $model = ManualDepositMethod::query()->findOrFail($method);
        $model->update(['enabled' => !$model->enabled]);
        return back()->with('success', 'Manual deposit method state updated.');
    }

    public function approve(Request $request, int $deposit, ManualDepositService $service): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['nullable','string','max:2000']]);
        try {
            $service->approve(ManualDeposit::query()->findOrFail($deposit), $request->user()->id, $data['admin_note'] ?? null);
            return back()->with('success','Manual deposit approved and wallet credited.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error','Manual deposit approval failed. Check the audit log before retrying.');
        }
    }

    public function reject(Request $request, int $deposit, ManualDepositService $service): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['required','string','min:3','max:2000']]);
        try {
            $service->reject(ManualDeposit::query()->findOrFail($deposit), $request->user()->id, $data['admin_note']);
            return back()->with('success','Manual deposit rejected.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error','Manual deposit rejection failed. Check the audit log before retrying.');
        }
    }

    public function proof(int $deposit)
    {
        $model = ManualDeposit::query()->findOrFail($deposit);
        abort_unless(Storage::disk('local')->exists($model->proof_path), 404);
        return Storage::disk('local')->download($model->proof_path, $model->proof_original_name ?: 'payment-proof');
    }
}
