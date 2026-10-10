<?php

namespace Semizzy\Addons\Payments\Services;

use App\Services\Finance\WalletCreditService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\Payments\Models\ManualDeposit;

final class ManualDepositService
{
    public function __construct(private WalletCreditService $wallets) {}

    public function approve(ManualDeposit $deposit, int $adminId, ?string $note = null): ManualDeposit
    {
        return DB::transaction(function () use ($deposit, $adminId, $note): ManualDeposit {
            $deposit = ManualDeposit::query()->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status === 'approved') {
                return $deposit;
            }

            if ($deposit->status !== 'pending') {
                throw new RuntimeException('Only pending manual deposits can be approved.');
            }

            if ((string) $deposit->currency !== 'NGN') {
                throw new RuntimeException('Manual deposit currently supports NGN only.');
            }

            $this->wallets->credit(
                $deposit->user()->firstOrFail(),
                $this->majorFromMinor((string) $deposit->amount_minor),
                'manual-deposit:'.$deposit->id,
                'MANUAL-DEP-'.$deposit->id,
                'manual_deposit',
                [
                    'manual_deposit_id' => $deposit->id,
                    'reference' => $deposit->reference,
                    'method_id' => $deposit->method_id,
                    'approved_by' => $adminId,
                    'currency' => $deposit->currency,
                ]
            );

            $deposit->forceFill([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'credited_at' => now(),
                'admin_note' => $note,
            ])->saveOrFail();

            return $deposit->fresh();
        });
    }

    public function reject(ManualDeposit $deposit, int $adminId, string $note): ManualDeposit
    {
        return DB::transaction(function () use ($deposit, $adminId, $note): ManualDeposit {
            $deposit = ManualDeposit::query()->lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== 'pending') {
                throw new RuntimeException('Only pending manual deposits can be rejected.');
            }

            $deposit->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ])->saveOrFail();

            return $deposit->fresh();
        });
    }

    private function majorFromMinor(string $minor): string
    {
        $minor = ltrim($minor, '0') ?: '0';
        if (strlen($minor) === 1) return '0.0'.$minor;
        if (strlen($minor) === 2) return '0.'.$minor;
        return substr($minor, 0, -2).'.'.substr($minor, -2);
    }
}
