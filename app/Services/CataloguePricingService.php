<?php

namespace App\Services;

use App\Models\PriceRule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class CataloguePricingService
{
    public function calculate(string|int|float $cost, ?int $productId = null, ?int $serviceId = null, ?int $categoryId = null, ?string $tier = 'USER'): string
    {
        $amount = BigDecimal::of((string) $cost);
        $tier = $tier ? strtoupper($tier) : null;

        $rules = PriceRule::query()
            ->where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
            })
            ->where(function ($q) use ($tier) {
                $q->whereNull('customer_tier')->when($tier, fn ($x) => $x->orWhere('customer_tier', $tier));
            })
            ->orderByRaw("CASE scope_type WHEN 'PRODUCT' THEN 1 WHEN 'SERVICE' THEN 2 WHEN 'CATEGORY' THEN 3 WHEN 'GLOBAL' THEN 4 ELSE 5 END")
            ->orderBy('priority')
            ->get();

        $rule = $rules->first(function (PriceRule $r) use ($productId, $serviceId, $categoryId) {
            return match ($r->scope_type) {
                'PRODUCT' => (int) $r->scope_id === $productId,
                'SERVICE' => (int) $r->scope_id === $serviceId,
                'CATEGORY' => (int) $r->scope_id === $categoryId,
                'GLOBAL' => $r->scope_id === null,
                default => false,
            };
        });

        if (! $rule) return $this->money($amount);

        $fixed = BigDecimal::of((string) ($rule->fixed_fee ?? '0'));
        $percent = BigDecimal::of((string) ($rule->percentage ?? '0'));

        $selling = match ($rule->rule_type) {
            'fixed' => $amount->plus($fixed),
            'percentage' => $amount->plus($amount->multipliedBy($percent)->dividedBy('100', 12, RoundingMode::HALF_UP)),
            'fixed_percentage' => $amount->plus($fixed)->plus($amount->multipliedBy($percent)->dividedBy('100', 12, RoundingMode::HALF_UP)),
            default => throw new InvalidArgumentException('Unsupported pricing rule type.'),
        };

        if ($rule->minimum_price !== null) $selling = $selling->isLessThan(BigDecimal::of((string) $rule->minimum_price)) ? BigDecimal::of((string) $rule->minimum_price) : $selling;
        if ($rule->maximum_price !== null) $selling = $selling->isGreaterThan(BigDecimal::of((string) $rule->maximum_price)) ? BigDecimal::of((string) $rule->maximum_price) : $selling;

        $increment = (int) ($rule->rounding_increment ?? 0);
        if ($increment > 0) {
            $selling = $selling->dividedBy((string) $increment, 0, RoundingMode::HALF_UP)->multipliedBy((string) $increment);
        }

        return $this->money($selling);
    }

    private function money(BigDecimal $value): string
    {
        return $value->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
