<?php

namespace Tests\Feature\P2p;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Semizzy\Addons\P2p\Models\P2pTradeOffer;
use Semizzy\Addons\P2p\Services\P2pTradingService;
use Tests\TestCase;

class P2pTradingDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private function listing(P2pTradingService $service, User $seller, string $side = 'sell')
    {
        return $service->createListing($seller->id, $side, 'mobile-data', '5000', '250', 'Regression-test listing');
    }

    public function test_only_listing_seller_can_reject_an_offer(): void
    {
        $service = app(P2pTradingService::class);
        $seller = User::factory()->create();
        $otherUser = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->listing($service, $seller);
        $offer = $service->createOffer($buyer->id, $listing->id, '1000', '250', null, 'p2p-auth-1');

        try {
            $service->rejectOffer($otherUser->id, $offer->id);
            $this->fail('A non-seller must not reject an offer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Only the listing owner can reject this offer.', $exception->getMessage());
        }

        $this->assertSame('pending', $offer->fresh()->status);
    }

    public function test_reusing_offer_idempotency_key_returns_one_database_record(): void
    {
        $service = app(P2pTradingService::class);
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->listing($service, $seller);

        $first = $service->createOffer($buyer->id, $listing->id, '1000', '250', null, 'p2p-idem-1');
        $second = $service->createOffer($buyer->id, $listing->id, '1000', '250', null, 'p2p-idem-1');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('p2p_trade_offers', 1);
    }

    public function test_reusing_offer_idempotency_key_with_different_amount_is_rejected(): void
    {
        $service = app(P2pTradingService::class);
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->listing($service, $seller);

        $service->createOffer($buyer->id, $listing->id, '1000', '250', null, 'p2p-idem-2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This idempotency key has already been used for a different offer.');

        $service->createOffer($buyer->id, $listing->id, '2000', '250', null, 'p2p-idem-2');
    }

    public function test_rejected_offer_cannot_be_rejected_again(): void
    {
        $service = app(P2pTradingService::class);
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = $this->listing($service, $seller);
        $offer = $service->createOffer($buyer->id, $listing->id, '1000', '250', null, 'p2p-state-1');

        $service->rejectOffer($seller->id, $offer->id);

        $this->assertSame('rejected', $offer->fresh()->status);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This offer is not pending.');

        $service->rejectOffer($seller->id, $offer->id);
    }

    public function test_only_offer_buyer_can_cancel_an_offer(): void
    {
        $service = app(P2pTradingService::class);
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $otherUser = User::factory()->create();
        $listing = $this->listing($service, $seller);
        $offer = $service->createOffer($buyer->id, $listing->id, '1000', '250', null, 'p2p-cancel-1');

        try {
            $service->cancelOffer($otherUser->id, $offer->id);
            $this->fail('A non-buyer must not cancel an offer.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Only the offer buyer can cancel it.', $exception->getMessage());
        }

        $this->assertSame('pending', $offer->fresh()->status);
    }
}
