<?php

namespace App\Services\Pricing;

use App\Models\ApiProvider;
use App\Models\PriceRule;
use App\Models\ServiceProduct;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class PriceEngine
{
    public function quote(ServiceProduct $product, string $customerTier = 'USER', ?string $at = null, ?ApiProvider $provider = null): array
    {
        $service = $product->service()->with('category')->first();

        if (! $product->enabled || ! $service || ! $service->enabled || ! $service->category?->enabled) {
            throw new InvalidArgumentException('Only enabled products under enabled services and categories can be quoted.');
        }

        $resolvedProvider = $this->resolveProviderCost($product, $provider);
        $cost = BigDecimal::of($resolvedProvider['cost']);
        $customerTier = strtoupper($customerTier);
        $this->validateCustomerTier($customerTier);
        $rule = $this->rules($product, $customerTier, $at)->first();

        if (! $rule) {
            throw new InvalidArgumentException('No active selling-price rule is configured for this product and customer tier.');
        }

        $price = $cost;

        if ($rule) {
            $percentage = BigDecimal::of((string) $rule->percentage);
            $fixed = BigDecimal::of((string) $rule->fixed_fee);

            if ($rule->rule_type === 'fixed') {
                $price = $cost->plus($fixed);
            } elseif ($rule->rule_type === 'percentage') {
                $price = $cost->plus($cost->multipliedBy($percentage)->dividedBy(100, 12, RoundingMode::HALF_UP));
            } else {
                $price = $cost
                    ->plus($cost->multipliedBy($percentage)->dividedBy(100, 12, RoundingMode::HALF_UP))
                    ->plus($fixed);
            }

            if ($rule->minimum_price !== null && $price->isLessThan(BigDecimal::of((string) $rule->minimum_price))) {
                $price = BigDecimal::of((string) $rule->minimum_price);
            }

            if ($rule->maximum_price !== null && $price->isGreaterThan(BigDecimal::of((string) $rule->maximum_price))) {
                $price = BigDecimal::of((string) $rule->maximum_price);
            }

            if ($rule->rounding_increment > 0) {
                $increment = BigDecimal::of((string) $rule->rounding_increment)->dividedBy(100, 6, RoundingMode::HALF_UP);
                $price = $price->dividedBy($increment, 0, RoundingMode::HALF_UP)->multipliedBy($increment);
            }
        }

        return [
            'provider_id' => $resolvedProvider['provider_id'],
            'provider_cost' => (string) $cost->toScale(6, RoundingMode::HALF_UP),
            'customer_price' => (string) $price->toScale(2, RoundingMode::HALF_UP),
            'rule_id' => $rule?->id,
            'currency' => $product->currency,
        ];
    }

    private function validateCustomerTier(string $tier): void
    {
        $allowed = ['USER', 'AGENT', 'RESELLER', 'MERCHANT', 'CUSTOM'];
        if (! in_array(strtoupper($tier), $allowed, true)) {
            throw new InvalidArgumentException('Unsupported customer tier.');
        }
    }

    private function rules(ServiceProduct $product, string $tier, ?string $at): \Illuminate\Database\Eloquent\Collection
    {
        $time = $at ? Carbon::parse($at) : now();

        return PriceRule::query()
            ->where('enabled', true)
            ->where(function ($query) use ($product): void {
                $query->where(fn ($nested) => $nested->where('scope_type', 'PRODUCT')->where('scope_id', $product->id))
                    ->orWhere(fn ($nested) => $nested->where('scope_type', 'SERVICE')->where('scope_id', $product->service_id))
                    ->orWhere(fn ($nested) => $nested->where('scope_type', 'CATEGORY')->where('scope_id', $product->service->category_id))
                    ->orWhere('scope_type', 'GLOBAL');
            })
            ->where(fn ($query) => $query->whereNull('customer_tier')->orWhere('customer_tier', $tier))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $time))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $time))
            ->orderByRaw("CASE scope_type WHEN 'PRODUCT' THEN 1 WHEN 'SERVICE' THEN 2 WHEN 'CATEGORY' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN customer_tier IS NULL THEN 2 ELSE 1 END')
            ->orderBy('priority')
            ->get();
    }

    /**
     * Resolve and return the exact verified provider whose cost is used for this quote.
     * A generic product cost is deliberately not a fallback: that can detach a quote
     * from the provider actually eligible to fulfill it.
     *
     * @return array{provider_id: int, cost: string}
     */
    private function resolveProviderCost(ServiceProduct $product, ?ApiProvider $provider): array
    {
        $query = $product->providerProducts()
            ->where('enabled', true)
            ->whereNotNull('provider_cost')
            ->where('currency', $product->currency)
            ->whereHas('provider', function ($providerQuery): void {
                $providerQuery->where('enabled', true)
                    ->where('paused', false)
                    ->where('integration_status', 'live_verified')
                    ->where('verification_status', 'live_verified');
            })
            ->whereHas('provider.serviceMappings', function ($mappingQuery) use ($product): void {
                $mappingQuery->where('enabled', true)
                    ->where(function ($nested) use ($product): void {
                        $nested->where('service_id', $product->service_id)
                            ->orWhere(fn ($legacy) => $legacy->whereNull('service_id')->where('service_key', $product->service->key));
                    });
            });

        if ($provider !== null) {
            $query->where('api_provider_id', $provider->id);
        }

        $mapping = $query->orderBy('provider_cost')->first();

        if (! $mapping || $mapping->provider_cost === null) {
            throw new InvalidArgumentException('No eligible live-verified provider cost is available for this product.');
        }

        return [
            'provider_id' => (int) $mapping->api_provider_id,
            'cost' => (string) $mapping->provider_cost,
        ];
    }
}
