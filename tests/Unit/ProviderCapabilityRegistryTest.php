<?php

namespace Tests\\Unit;

use App\\Models\\ApiProvider;
use App\\Services\\Providers\\ProviderCapabilityRegistry;
use App\\Services\\Providers\\ProviderUrlGuard;
use App\\Services\\Providers\\RestJsonProviderAdapter;
use InvalidArgumentException;
use PHPUnit\\Framework\\TestCase;

class ProviderCapabilityRegistryTest extends TestCase
{
    public function test_finance_and_service_operations_are_supported_by_the_core_rest_adapter(): void
    {
        $adapter = new RestJsonProviderAdapter(new ProviderUrlGuard());

        foreach ([
            'stock_market_data',
            'stock_order_create',
            'fx_rate_quote',
            'fx_trade_create',
            'investment_subscribe',
            'loan_application_submit',
            'crypto_order_create',
            'gift_card_purchase',
            'travel_booking_create',
            'insurance_policy_issue',
            'card_issue',
            'cac_filing_submit',
            'domain_register',
        ] as $operation) {
            $this->assertTrue($adapter->supports($operation), $operation . ' should be supported by configured Core provider endpoints.');
        }

        $this->assertFalse($adapter->supports('made_up_provider_operation'));
    }

    public function test_money_moving_provider_capabilities_require_status_or_requery_support(): void
    {
        $registry = new ProviderCapabilityRegistry();
        $provider = new ApiProvider([
            'capabilities' => ['stock_order_create'],
            'enabled' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('stock_order_create requires stock_order_status or generic transaction_status requery support.');
        $registry->validate($provider);
    }

    public function test_verified_mutations_require_idempotency_and_live_provider_verification(): void
    {
        $registry = new ProviderCapabilityRegistry();

        $this->assertTrue($registry->requiresIdempotency('stock_order_create'));
        $this->assertTrue($registry->requiresIdempotency('investment_subscribe'));
        $this->assertTrue($registry->requiresIdempotency('gift_card_purchase'));
        $this->assertFalse($registry->requiresIdempotency('stock_market_data'));
        $this->assertTrue($registry->requiresLiveVerification('fx_trade_create'));
        $this->assertFalse($registry->requiresLiveVerification('fx_rate_quote'));
    }

    public function test_mutating_capability_passes_validation_when_status_support_is_declared(): void
    {
        $registry = new ProviderCapabilityRegistry();
        $provider = new ApiProvider([
            'capabilities' => ['stock_order_create', 'stock_order_status'],
            'enabled' => false,
        ]);

        $registry->validate($provider);
        $this->assertTrue($registry->supports($provider, 'stock_order_create'));
        $this->assertTrue($registry->supports($provider, 'stock_order_status'));
    }
    public function test_empty_transaction_status_response_remains_unknown(): void
    {
        $adapter = new RestJsonProviderAdapter(new ProviderUrlGuard());
        $normalizer = new \\ReflectionMethod(RestJsonProviderAdapter::class, 'normalizeStatus');
        $normalizer->setAccessible(true);

        $this->assertSame('UNKNOWN', $normalizer->invoke($adapter, ['data' => []], 'transaction_status'));
        $this->assertSame('UNKNOWN', $normalizer->invoke($adapter, ['data' => []], 'stock_order_status'));
        $this->assertSame('ACCEPTED', $normalizer->invoke($adapter, ['data' => []], 'catalogue_retrieval'));
    }

}
