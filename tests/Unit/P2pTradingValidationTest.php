<?php

namespace Tests\Unit;

use RuntimeException;
use Semizzy\Addons\P2p\Services\P2pTradingService;
use Tests\TestCase;

class P2pTradingValidationTest extends TestCase
{
    public function test_listing_rejects_an_unsupported_side_before_database_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Listing side must be sell or buy.');

        app(P2pTradingService::class)->createListing(10, 'swap', 'mobile-data', '1000', '100', null);
    }

    public function test_listing_rejects_an_empty_asset_key_before_database_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Asset/service key is required.');

        app(P2pTradingService::class)->createListing(10, 'sell', '  ', '1000', '100', null);
    }

    public function test_listing_rejects_zero_minor_unit_amount_before_database_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Amount must be a positive minor-unit integer.');

        app(P2pTradingService::class)->createListing(10, 'sell', 'mobile-data', '0', '100', null);
    }

    public function test_offer_rejects_decimal_major_unit_amount_before_database_access(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Amount must be a positive minor-unit integer.');

        app(P2pTradingService::class)->createOffer(20, 1, '10.50', '100', null, 'offer-key-1');
    }
}
