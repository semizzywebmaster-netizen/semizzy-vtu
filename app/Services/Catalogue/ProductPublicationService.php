<?php

namespace App\Services\Catalogue;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\ProviderServiceProduct;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Services\Pricing\PriceEngine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductPublicationService
{
    private const TIERS = ['USER', 'AGENT', 'RESELLER', 'MERCHANT'];

    public function __construct(private readonly PriceEngine $prices)
    {
    }

    public function assess(ServiceProduct $product): array
    {
        $product->loadMissing('service.category');
        $blockers = [];
        $quotes = [];

        if (! $product->service) {
            $blockers[] = 'The product must belong to an existing platform service.';
        } elseif (! $product->service->enabled) {
            $blockers[] = 'Enable the platform service before publishing this product.';
        } elseif (! $product->service->category || ! $product->service->category->enabled) {
            $blockers[] = 'Enable the platform category before publishing this product.';
        }

        $candidate = ProviderServiceProduct::query()
            ->with('provider')
            ->where('service_product_id', $product->id)
            ->where('enabled', true)
            ->whereNotNull('provider_cost')
            ->where('currency', strtoupper($product->currency))
            ->whereHas('provider', function ($query): void {
                $query->where('enabled', true)
                    ->where('paused', false)
                    ->where('verification_status', 'live_verified')
                    ->where('integration_status', 'live_verified');
            })
            ->whereHas('provider.serviceMappings', function ($query) use ($product): void {
                $query->where('enabled', true)
                    ->where(function ($nested) use ($product): void {
                        $nested->where('service_id', $product->service_id)
                            ->orWhere(fn ($legacy) => $legacy->whereNull('service_id')->where('service_key', $product->service?->key ?? ''));
                    })
                    ->whereJsonContains('capabilities', 'transaction_initiation');
            })
            ->orderBy('provider_cost')
            ->first();

        if (! $candidate) {
            $blockers[] = 'No enabled provider product is mapped to an enabled, unpaused, live-verified provider with transaction-initiation routing for this platform service.';
        } else {
            if (! $candidate->last_synced_at || $candidate->last_synced_at->lt(now()->subHours(24))) {
                $blockers[] = 'The selected provider source cost is missing or older than 24 hours. Refresh the provider catalogue before publishing.';
            }

            if (trim((string) $candidate->provider_product_id) === '') {
                $blockers[] = 'The selected provider product is missing its external product ID.';
            } else {
                $duplicate = ProviderServiceProduct::query()
                    ->where('api_provider_id', $candidate->api_provider_id)
                    ->where('provider_product_id', $candidate->provider_product_id)
                    ->where('service_product_id', '!=', $product->id)
                    ->exists();
                if ($duplicate) {
                    $blockers[] = 'This provider external product ID is already mapped to a different platform product. Resolve the duplicate mapping first.';
                }
            }
        }

        if ($product->service?->enabled && $product->service?->category?->enabled && $candidate) {
            $quoteProduct = clone $product;
            $quoteProduct->enabled = true;

            foreach (self::TIERS as $tier) {
                try {
                    $quote = $this->prices->quote($quoteProduct, $tier);
                    if ((int) $quote['provider_id'] !== (int) $candidate->api_provider_id) {
                        $blockers[] = 'Provider cost selection changed while checking prices. Refresh the catalogue and retry publication.';
                        continue;
                    }

                    $cost = BigDecimal::of((string) $quote['provider_cost']);
                    $selling = BigDecimal::of((string) $quote['customer_price']);
                    if ($selling->isLessThan($cost)) {
                        $blockers[] = $tier . ' selling price is below the resolved provider cost. Adjust the price rule or explicitly revise the platform margin policy.';
                    }

                    $quotes[$tier] = [
                        'provider_cost' => (string) $cost->toScale(2, RoundingMode::HALF_UP),
                        'selling_price' => (string) $selling->toScale(2, RoundingMode::HALF_UP),
                        'gross_profit' => (string) $selling->minus($cost)->toScale(2, RoundingMode::HALF_UP),
                        'currency' => $quote['currency'],
                        'provider_id' => (int) $quote['provider_id'],
                        'price_rule_id' => $quote['rule_id'],
                    ];
                } catch (InvalidArgumentException $exception) {
                    $blockers[] = $tier . ' selling price cannot be resolved: ' . $exception->getMessage();
                }
            }
        }

        $blockers = array_values(array_unique($blockers));

        return [
            'ready' => $blockers === [] && count($quotes) === count(self::TIERS),
            'blockers' => $blockers,
            'tiers' => self::TIERS,
            'tier_quotes' => $quotes,
            'provider' => $candidate ? [
                'id' => (int) $candidate->api_provider_id,
                'name' => $candidate->provider?->display_name ?? $candidate->provider?->identifier ?? 'Provider',
                'external_product_id' => $candidate->provider_product_id,
                'source_cost' => (string) $candidate->provider_cost,
                'currency' => $candidate->currency,
                'last_synced_at' => $candidate->last_synced_at?->toISOString(),
            ] : null,
        ];
    }

    public function publish(ServiceProduct $product, User $actor): array
    {
        return DB::transaction(function () use ($product, $actor): array {
            $locked = ServiceProduct::query()->lockForUpdate()->findOrFail($product->id);
            $locked->providerProducts()->lockForUpdate()->get();
            ProviderServiceMapping::query()->where('service_id', $locked->service_id)->lockForUpdate()->get();
            ApiProvider::query()
                ->whereIn('id', $locked->providerProducts()->pluck('api_provider_id')->unique())
                ->lockForUpdate()->get();

            $assessment = $this->assess($locked);
            if (! $assessment['ready']) {
                $locked->update([
                    'enabled' => false,
                    'publication_status' => $locked->publication_status === 'published' ? 'unpublished' : 'draft',
                    'publication_blockers' => $assessment['blockers'],
                ]);

                return ['published' => false, 'product' => $locked->fresh(), 'assessment' => $assessment];
            }

            $locked->update([
                'enabled' => true,
                'publication_status' => 'published',
                'published_at' => now(),
                'published_by' => $actor->id,
                'publication_blockers' => null,
            ]);

            return ['published' => true, 'product' => $locked->fresh(), 'assessment' => $assessment];
        }, 3);
    }

    public function unpublish(ServiceProduct $product): ServiceProduct
    {
        return DB::transaction(function () use ($product): ServiceProduct {
            $locked = ServiceProduct::query()->lockForUpdate()->findOrFail($product->id);
            $locked->update([
                'enabled' => false,
                'publication_status' => 'unpublished',
            ]);

            return $locked->fresh();
        }, 3);
    }
}
