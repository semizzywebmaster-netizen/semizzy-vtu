<?php

namespace Semizzy\Addons\ApiProviderPlatform\Services;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\ProviderServiceProduct;
use App\Models\ServiceProduct;
use App\Services\Pricing\PriceEngine;
use App\Services\Audit\AuditLogger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

final class ProductPublicationService
{
    private const REQUIRED_TIERS = ['USER', 'AGENT', 'RESELLER', 'MERCHANT'];
    private const MAX_SOURCE_AGE_HOURS = 24;

    public function __construct(
        private readonly PriceEngine $priceEngine,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Publish only after server-side checks pass. A failure is persisted as a draft
     * with explicit blockers; source sync never changes publication or tier pricing.
     *
     * @return array{published: bool, blockers: array<int, string>, product: ServiceProduct}
     */
    public function publish(ServiceProduct $product, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($product, $actorId): array {
            $locked = ServiceProduct::query()->lockForUpdate()->findOrFail($product->id);
            $blockers = $this->readinessBlockers($locked);

            if ($blockers !== []) {
                $locked->forceFill([
                    'enabled' => false,
                    'publication_status' => 'draft',
                    'publication_blockers' => $blockers,
                ])->save();

                $this->auditLogger->record('provider_platform.product_publication_blocked', $locked, [
                    'product_id' => $locked->id,
                    'blockers' => $blockers,
                ]);

                return ['published' => false, 'blockers' => $blockers, 'product' => $locked->refresh()];
            }

            $locked->forceFill([
                'enabled' => true,
                'publication_status' => 'published',
                'published_at' => now(),
                'published_by' => $actorId,
                'publication_blockers' => null,
            ])->save();

            $this->auditLogger->record('provider_platform.product_published', $locked, [
                'product_id' => $locked->id,
                'service_id' => $locked->service_id,
                'tier_checks' => self::REQUIRED_TIERS,
            ]);

            return ['published' => true, 'blockers' => [], 'product' => $locked->refresh()];
        });
    }

    public function unpublish(ServiceProduct $product, ?int $actorId = null): ServiceProduct
    {
        return DB::transaction(function () use ($product, $actorId): ServiceProduct {
            $locked = ServiceProduct::query()->lockForUpdate()->findOrFail($product->id);
            $locked->forceFill([
                'enabled' => false,
                'publication_status' => 'unpublished',
                'publication_blockers' => [],
            ])->save();

            $this->auditLogger->record('provider_platform.product_unpublished', $locked, [
                'product_id' => $locked->id,
                'actor_id' => $actorId,
            ]);

            return $locked->refresh();
        });
    }

    /** @return array<int, string> */
    public function readinessBlockers(ServiceProduct $product): array
    {
        $blockers = [];
        $service = $product->service()->with('category')->first();

        if (!$service || !$service->enabled) {
            $blockers[] = 'The platform service must exist and be enabled.';
        }
        if (!$service?->category || !$service->category->enabled) {
            $blockers[] = 'The platform service category must be enabled.';
        }
        if (!$product->exists || trim((string) $product->key) === '' || trim((string) $product->name) === '') {
            $blockers[] = 'A saved product with a valid key and name is required.';
        }

        $providerProducts = $product->providerProducts()->with('provider')->get();
        $eligibleSourceFound = false;
        $pricingProvider = null;

        foreach ($providerProducts as $providerProduct) {
            $provider = $providerProduct->provider;
            if (!$provider || !$providerProduct->enabled || !$providerProduct->provider_product_id
                || trim((string) $providerProduct->provider_product_id) === ''
                || $providerProduct->provider_cost === null
                || strtoupper((string) $providerProduct->currency) !== strtoupper((string) $product->currency)
                || !$providerProduct->last_synced_at
                || $providerProduct->last_synced_at->lt(now()->subHours(self::MAX_SOURCE_AGE_HOURS))) {
                continue;
            }

            if (!$provider->enabled || $provider->paused
                || $provider->verification_status !== 'live_verified'
                || $provider->integration_status !== 'live_verified') {
                continue;
            }

            $mappingExists = ProviderServiceMapping::query()
                ->where('api_provider_id', $provider->id)
                ->where('enabled', true)
                ->where(function ($query) use ($service): void {
                    $query->where('service_id', $service?->id)
                        ->orWhere(function ($legacy) use ($service): void {
                            $legacy->whereNull('service_id')->where('service_key', $service?->key);
                        });
                })
                ->whereJsonContains('capabilities', 'transaction_initiation')
                ->exists();

            $duplicateExternalId = $providerProduct->provider_product_id !== null
                && ProviderServiceProduct::query()
                    ->where('api_provider_id', $provider->id)
                    ->where('provider_product_id', $providerProduct->provider_product_id)
                    ->where('service_product_id', '!=', $product->id)
                    ->exists();

            if ($mappingExists && !$duplicateExternalId) {
                $eligibleSourceFound = true;
                $pricingProvider = $provider;
                break;
            }
        }

        if (!$eligibleSourceFound) {
            $blockers[] = 'A fresh (within 24 hours), currency-matched source cost and unique external product ID from an enabled, unpaused, live-verified provider with an enabled transaction route are required.';
        }

        // PriceEngine is the single source of truth for tier pricing. Temporarily enable
        // only this in-memory model for evaluation; no sellable state is saved unless all
        // checks pass.
        if ($service && $service->enabled && $service->category?->enabled) {
            $product->setRelation('service', $service);
            $product->enabled = true;
            foreach (self::REQUIRED_TIERS as $tier) {
                try {
                    $quote = $this->priceEngine->quote($product, $tier, null, $pricingProvider);
                    if (isset($quote['provider_cost'], $quote['customer_price'])
                        && BigDecimal::of((string) $quote['customer_price'])->isLessThan(BigDecimal::of((string) $quote['provider_cost']))) {
                        $blockers[] = "The {$tier} selling price is below provider cost.";
                    }
                } catch (\Throwable $exception) {
                    $blockers[] = "A valid live provider route and selling-price rule are missing for the {$tier} tier.";
                }
            }
        } else {
            $blockers[] = 'Tier price checks cannot run until the service and category are enabled.';
        }

        return array_values(array_unique($blockers));
    }
}
