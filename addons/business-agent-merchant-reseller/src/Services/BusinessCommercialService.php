<?php
namespace Addons\BusinessAgentMerchantReseller\Services;

use Addons\BusinessAgentMerchantReseller\Models\BusinessPartner;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BusinessCommercialService
{
    public function assertCanTransact(BusinessPartner $partner, int $amountMinor): void
    {
        if ($partner->status !== 'active') {
            throw new RuntimeException('Business partner is not active.');
        }
        if ($amountMinor < 0) {
            throw new RuntimeException('Invalid transaction amount.');
        }

        $this->assertBalance($partner);

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $counter = DB::table('business_limit_counters')
            ->where('business_partner_id', $partner->id)
            ->where('period_date', $today)
            ->lockForUpdate()
            ->first();

        $dailyUsed = (int) ($counter->daily_used_minor ?? 0);

        $monthlyUsed = (int) DB::table('business_limit_counters')
            ->where('business_partner_id', $partner->id)
            ->whereBetween('period_date', [$monthStart, $today])
            ->sum('daily_used_minor');

        if ($partner->daily_limit_minor !== null && $dailyUsed + $amountMinor > (int) $partner->daily_limit_minor) {
            throw new RuntimeException('Daily business transaction limit exceeded.');
        }

        if ($partner->monthly_limit_minor !== null && $monthlyUsed + $amountMinor > (int) $partner->monthly_limit_minor) {
            throw new RuntimeException('Monthly business transaction limit exceeded.');
        }
    }

    public function record(BusinessPartner $partner, int $amountMinor, string $serviceKey = 'unknown', ?string $productKey = null, string $transactionKey = ''): void
    {
        if ($amountMinor < 0) {
            throw new RuntimeException('Invalid transaction amount.');
        }
        if ($transactionKey === '') {
            throw new RuntimeException('A transaction key is required for commercial settlement recording.');
        }

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        DB::table('business_limit_counters')->updateOrInsert(
            ['business_partner_id' => $partner->id, 'period_date' => $today],
            [
                'period_month' => $monthStart,
                'daily_used_minor' => 0,
                'monthly_used_minor' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('business_limit_counters')
            ->where('business_partner_id', $partner->id)
            ->where('period_date', $today)
            ->lockForUpdate()
            ->increment('daily_used_minor', $amountMinor, ['updated_at' => now()]);
        app(BusinessCommissionService::class)->accrue($partner, $serviceKey, $productKey, $amountMinor, $transactionKey);
    }

    public function pricing(BusinessPartner $partner, string $serviceKey, ?string $productKey = null): ?object
    {
        return DB::table('business_pricing_rules')
            ->where('business_partner_id', $partner->id)
            ->where('service_key', $serviceKey)
            ->where('enabled', true)
            ->where(function ($query) use ($productKey): void {
                $query->whereNull('product_key');
                if ($productKey !== null) {
                    $query->orWhere('product_key', $productKey);
                }
            })
            ->when($productKey !== null, fn ($query) => $query->orderByRaw('CASE WHEN product_key = ? THEN 0 ELSE 1 END', [$productKey]))
            ->orderBy('id')
            ->first();
    }

    public function applyPricing(int $baseMinor, ?object $rule): int
    {
        if ($rule === null) {
            return $baseMinor;
        }

        $type = strtolower((string) ($rule->rule_type ?? 'markup'));
        $adjustment = $rule->amount_minor !== null
            ? (int) $rule->amount_minor
            : ($rule->rate_bps !== null
                ? intdiv($baseMinor * (int) $rule->rate_bps + 5000, 10000)
                : 0);

        // Commission is tracked as a commercial rule but must not silently
        // change the customer's payable amount.
        $value = match ($type) {
            'discount' => $baseMinor - $adjustment,
            'commission' => $baseMinor,
            default => $baseMinor + $adjustment,
        };

        if ($rule->min_amount_minor !== null) {
            $value = max($value, (int) $rule->min_amount_minor);
        }
        if ($rule->max_amount_minor !== null) {
            $value = min($value, (int) $rule->max_amount_minor);
        }

        return max(0, $value);
    }

    private function assertBalance(BusinessPartner $partner): void
    {
        if ((int) $partner->minimum_balance_minor <= 0) {
            return;
        }

        $user = $partner->relationLoaded('user') ? $partner->user : $partner->user()->first();
        $wallet = $user?->wallet;

        if (!$wallet) {
            throw new RuntimeException('Business wallet is unavailable.');
        }

        $available = (int) ($wallet->available_balance_minor ?? $wallet->balance_minor ?? 0);
        if ($available < (int) $partner->minimum_balance_minor) {
            throw new RuntimeException('Minimum balance requirement not met.');
        }
    }
}
