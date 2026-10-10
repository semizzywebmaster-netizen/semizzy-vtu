<?php

namespace Semizzy\Addons\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Semizzy\Addons\Payments\Models\ManualDeposit;
use Semizzy\Addons\Payments\Models\ManualDepositMethod;

final class ManualDepositController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Payments/ManualDeposit', [
            'methods' => ManualDepositMethod::query()
                ->where('enabled', true)
                ->where('currency', 'NGN')
                ->orderBy('priority')->orderBy('id')
                ->get(['id','name','bank_name','account_name','account_number','instructions','currency']),
            'deposits' => ManualDeposit::query()
                ->where('user_id', $request->user()->id)
                ->with('method:id,name,bank_name,account_name,account_number')
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (ManualDeposit $deposit): array => [
                    'id' => $deposit->id,
                    'reference' => $deposit->reference,
                    'amount_minor' => $deposit->amount_minor,
                    'currency' => $deposit->currency,
                    'status' => $deposit->status,
                    'method' => $deposit->method,
                    'user_note' => $deposit->user_note,
                    'admin_note' => $deposit->admin_note,
                    'submitted_at' => $deposit->submitted_at?->toISOString(),
                    'reviewed_at' => $deposit->reviewed_at?->toISOString(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'method_id' => ['required','integer','exists:manual_deposit_methods,id'],
            'amount' => ['required','string','regex:/^\d+(?:\.\d{1,2})?$/','max:20'],
            'proof' => ['required','file','mimes:jpg,jpeg,png,webp,pdf','max:5120'],
            'user_note' => ['nullable','string','max:1000'],
        ]);

        $method = ManualDepositMethod::query()
            ->whereKey($data['method_id'])
            ->where('enabled', true)
            ->where('currency', 'NGN')
            ->firstOrFail();

        $amountMinor = $this->minorFromMajor($data['amount']);
        if ($amountMinor === '0') {
            return back()->withErrors(['amount' => 'Deposit amount must be greater than zero.']);
        }

        $path = $request->file('proof')->store('manual-deposit-proofs', 'local');

        ManualDeposit::create([
            'user_id' => $request->user()->id,
            'wallet_account_id' => $request->user()->walletAccounts()->where('currency','NGN')->where('status','active')->value('id')
                ?? throw new \RuntimeException('An active NGN wallet is required before making a manual deposit.'),
            'method_id' => $method->id,
            'reference' => 'MD-'.strtoupper(Str::random(18)),
            'amount_minor' => $amountMinor,
            'currency' => 'NGN',
            'status' => 'pending',
            'proof_path' => $path,
            'proof_original_name' => $request->file('proof')->getClientOriginalName(),
            'user_note' => $data['user_note'] ?? null,
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Manual deposit submitted. It will credit your wallet only after admin approval.');
    }

    public function proof(Request $request, int $deposit)
    {
        $model = ManualDeposit::query()->where('user_id',$request->user()->id)->findOrFail($deposit);
        abort_unless(Storage::disk('local')->exists($model->proof_path), 404);

        return Storage::disk('local')->download($model->proof_path, $model->proof_original_name ?: 'payment-proof');
    }

    private function minorFromMajor(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');
        $whole = ltrim($whole, '0') ?: '0';
        $minor = ltrim($whole.$fraction, '0') ?: '0';
        return $minor;
    }
}
