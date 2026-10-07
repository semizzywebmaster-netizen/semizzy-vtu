<?php

namespace App\Services;

use App\Models\KycLookupCharge;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Services\Providers\ProviderManager;
use App\Services\System\SystemSettingsService;
use App\Services\System\FeatureControlService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class KycLookupBillingService
{
    public function __construct(
        private ProviderManager $providers,
        private SystemSettingsService $settings,
        private FeatureControlService $features,
        private AuditLogger $audit,
    ) {}

    public function lookup(User $user, string $identityType, string $identityNumber, ?string $idempotencyKey = null): array
    {
        $type = strtolower(trim($identityType));
        if (!in_array($type, ['bvn', 'nin'], true)) {
            throw new RuntimeException('Only BVN and NIN provider lookups are billable KYC lookups.');
        }

        if (!$this->features->enabled('kyc.enabled')) throw new RuntimeException('KYC is temporarily unavailable.');
        if (!$this->features->enabled('kyc.lookup.'.$type)) throw new RuntimeException(strtoupper($type).' verification lookup is temporarily unavailable.');

        $identity = trim($identityNumber);
        if ($identity === '') {
            throw new RuntimeException('Identity number is required.');
        }

        $charge = $this->configuredCharge($type);
        if ($charge <= 0) {
            throw new RuntimeException('The administrator has not configured a valid '.$type.' lookup charge.');
        }

        $hash = hash('sha256', strtoupper($type).'|'.$identity);
        $key = trim((string) ($idempotencyKey ?: ''));
        if ($key === '') $key = (string) Str::uuid();
        if (!preg_match('/^[A-Za-z0-9._:-]{8,160}$/', $key)) throw new RuntimeException('Invalid KYC lookup idempotency key.');
        $operationKey = 'kyc:lookup:'.$user->id.':'.$type.':'.$key;

        $existing = KycLookupCharge::query()->where('operation_key', $operationKey)->first();
        if ($existing && in_array($existing->status, ['charged', 'completed', 'processing'], true)) {
            return [
                'status' => $existing->status,
                'charged' => true,
                'charge_minor' => (int) $existing->charge_minor,
                'currency' => $existing->currency,
                'provider_reference' => $existing->provider_reference,
                'data' => $existing->metadata['provider_data'] ?? [],
            ];
        }

        $attempt = KycLookupCharge::query()->firstOrCreate(
            ['operation_key' => $operationKey],
            [
                'user_id' => $user->id,
                'identity_type' => $type,
                'identity_hash' => $hash,
                'charge_minor' => $charge,
                'currency' => 'NGN',
                'status' => 'pending',
                'metadata' => ['billing_source' => 'admin_configured_provider_charge'],
            ]
        );

        if ($attempt->status === 'refunded') {
            $attempt->forceFill(['status' => 'pending', 'refunded_at' => null])->saveOrFail();
        }

        DB::transaction(function () use ($user, $attempt, $charge, $type): void {
            $attempt = KycLookupCharge::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== 'pending') {
                return;
            }

            $wallet = WalletAccount::query()
                ->where('user_id', $user->id)
                ->where('currency', 'NGN')
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new RuntimeException('Your NGN wallet is not available.');
            }

            $before = (int) $wallet->available_minor;
            if ($before < $charge) {
                throw new RuntimeException('Insufficient wallet balance for the '.$type.' verification charge.');
            }

            $after = $before - $charge;
            $reference = 'KYC-'.strtoupper(Str::random(12));

            $wallet->forceFill(['available_minor' => (string) $after])->saveOrFail();
            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => 'kyc:charge:'.$attempt->operation_key,
                'reference' => $reference,
                'type' => 'debit',
                'amount_minor' => (string) $charge,
                'currency' => 'NGN',
                'available_before_minor' => (string) $before,
                'available_after_minor' => (string) $after,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => [
                    'purpose' => 'kyc_lookup',
                    'identity_type' => $type,
                    'operation_key' => $attempt->operation_key,
                    'exact_configured_charge' => $charge,
                ],
            ]);

            $attempt->forceFill([
                'status' => 'charged',
                'wallet_reference' => $reference,
                'charged_at' => now(),
            ])->saveOrFail();
        });

        try {
            $result = $this->providers->execute(
                'kyc.'.$type,
                'kyc_verification',
                ['identity_type' => $type, 'identity_number' => $identity],
                $attempt->operation_key
            );
        } catch (\Throwable $e) {
            $this->refund($attempt->id, 'provider_unavailable', $e->getMessage());
            throw $e;
        }

        if ($result->accepted) {
            $attempt->forceFill([
                'status' => 'completed',
                'provider_id' => $result->providerId,
                'provider_reference' => $result->providerReference,
                'provider_status' => $result->status,
                'metadata' => array_merge((array) $attempt->metadata, ['provider_data' => $result->data]),
            ])->saveOrFail();
            try { $this->audit->record('kyc.lookup.completed', $user->id, ['identity_type'=>$type,'charge_minor'=>$charge,'reference'=>$attempt->wallet_reference,'provider_reference'=>$result->providerReference], request()); } catch (\Throwable $e) { report($e); }

            return [
                'status' => 'completed',
                'charged' => true,
                'charge_minor' => $charge,
                'currency' => 'NGN',
                'provider_reference' => $result->providerReference,
                'data' => $result->data,
            ];
        }

        $status = strtoupper((string) $result->status);
        if (in_array($status, ['UNKNOWN', 'PENDING', 'PROCESSING'], true) || $result->duplicateRisk) {
            $attempt->forceFill([
                'status' => 'processing',
                'provider_id' => $result->providerId,
                'provider_reference' => $result->providerReference,
                'provider_status' => $result->status,
                'metadata' => array_merge((array) $attempt->metadata, ['provider_message' => $result->message]),
            ])->saveOrFail();

            return [
                'status' => 'processing',
                'charged' => true,
                'charge_minor' => $charge,
                'currency' => 'NGN',
                'provider_reference' => $result->providerReference,
                'data' => [],
            ];
        }

        $this->refund($attempt->id, 'provider_failed', $result->message);
        throw new RuntimeException($result->message ?: 'KYC provider rejected the lookup.');
    }

    private function configuredCharge(string $type): int
    {
        $settings = $this->settings->all();
        $value = $settings['kyc_'.$type.'_lookup_charge_minor'] ?? 0;
        if (is_numeric($value) && (int) $value > 0) return (int) $value;
        $decimal = $settings['kyc_'.$type.'_lookup_charge'] ?? null;
        if (is_numeric($decimal) && (float) $decimal > 0) return (int) round((float) $decimal * 100);
        return 0;
    }

    private function refund(int $attemptId, string $reason, ?string $message = null): void
    {
        DB::transaction(function () use ($attemptId, $reason, $message): void {
            $attempt = KycLookupCharge::query()->lockForUpdate()->findOrFail($attemptId);
            if ($attempt->status === 'refunded') return;
            if (!$attempt->wallet_reference) {
                $attempt->forceFill(['status' => 'refunded', 'refunded_at' => now()])->saveOrFail();
                return;
            }

            $wallet = WalletAccount::query()->where('id', $attempt->user->walletAccounts()->where('currency', $attempt->currency)->value('id'))->lockForUpdate()->first();
            if (!$wallet) throw new RuntimeException('Unable to locate wallet for KYC charge reversal.');

            $before = (int) $wallet->available_minor;
            $after = $before + (int) $attempt->charge_minor;
            $reference = 'KRF-'.strtoupper(Str::random(12));

            $wallet->forceFill(['available_minor' => (string) $after])->saveOrFail();
            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => 'kyc:refund:'.$attempt->operation_key,
                'reference' => $reference,
                'type' => 'credit',
                'amount_minor' => (string) $attempt->charge_minor,
                'currency' => $attempt->currency,
                'available_before_minor' => (string) $before,
                'available_after_minor' => (string) $after,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => ['purpose' => 'kyc_lookup_refund', 'reason' => $reason, 'message' => $message],
            ]);

            $attempt->forceFill([
                'status' => 'refunded',
                'refunded_at' => now(),
                'metadata' => array_merge((array) $attempt->metadata, ['refund_reason' => $reason]),
            ])->saveOrFail();
            try { $this->audit->record('kyc.lookup.refunded', $attempt->user_id, ['identity_type'=>$attempt->identity_type,'charge_minor'=>$attempt->charge_minor,'reason'=>$reason,'wallet_reference'=>$attempt->wallet_reference], request()); } catch (\Throwable $e) { report($e); }
        });
    }
}