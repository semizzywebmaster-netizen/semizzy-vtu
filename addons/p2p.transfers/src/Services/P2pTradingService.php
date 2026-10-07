<?php

namespace Semizzy\Addons\P2p\Services;

use App\Models\Addon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Escrow\Models\EscrowTransaction;
use Semizzy\Addons\Escrow\Services\EscrowService;
use Semizzy\Addons\P2p\Models\P2pTradeListing;
use Semizzy\Addons\P2p\Models\P2pTradeOffer;

final class P2pTradingService
{
    private function settings(): array
    {
        $schema = Addon::query()->where('identifier', 'p2p.transfers')->value('settings_schema') ?? [];
        return collect(is_array($schema) ? $schema : [])->mapWithKeys(
            fn ($item) => [($item['key'] ?? '') => $item['default'] ?? null]
        )->all();
    }

    private function positive(string $value): void
    {
        if (!preg_match('/^\d+$/', $value) || (int) $value <= 0) {
            throw new RuntimeException('Amount must be a positive minor-unit integer.');
        }
    }

    public function createListing(int $sellerId, string $side, string $assetKey, string $amountMinor, string $priceMinor, ?string $description): P2pTradeListing
    {
        $side = strtolower(trim($side));
        if (!in_array($side, ['sell', 'buy'], true)) throw new RuntimeException('Listing side must be sell or buy.');
        $assetKey = trim($assetKey);
        if ($assetKey === '') throw new RuntimeException('Asset/service key is required.');
        $this->positive($amountMinor);
        $this->positive($priceMinor);
        $settings = $this->settings();
        if ((int) $amountMinor > (int) ($settings['max_trade_minor'] ?? 1000000000)) {
            throw new RuntimeException('Listing amount exceeds the configured P2P trading limit.');
        }

        return P2pTradeListing::create([
            'seller_id' => $sellerId,
            'side' => $side,
            'asset_key' => $assetKey,
            'currency' => strtoupper((string) ($settings['default_currency'] ?? 'NGN')),
            'amount_minor' => $amountMinor,
            'price_minor' => $priceMinor,
            'status' => 'open',
            'description' => $description,
            'metadata' => ['addon' => 'p2p.transfers'],
        ]);
    }

    public function createOffer(int $buyerId, int $listingId, string $amountMinor, string $priceMinor, ?string $note, string $idempotencyKey): P2pTradeOffer
    {
        $this->positive($amountMinor);
        $this->positive($priceMinor);
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') throw new RuntimeException('A valid idempotency key is required.');

        $settings = $this->settings();
        $expiryHours = max(1, (int) ($settings['offer_expiry_hours'] ?? 24));

        try {
            return DB::transaction(function () use ($buyerId, $listingId, $amountMinor, $priceMinor, $note, $idempotencyKey, $expiryHours) {
                $existing = P2pTradeOffer::where('buyer_id', $buyerId)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) return $existing;

                $listing = P2pTradeListing::lockForUpdate()->findOrFail($listingId);
                if ($listing->status !== 'open') throw new RuntimeException('This listing is no longer open.');
                if ($listing->seller_id === $buyerId) throw new RuntimeException('You cannot make an offer on your own listing.');
                if ((int) $amountMinor > (int) $listing->amount_minor) throw new RuntimeException('Offer amount exceeds the listing amount.');

                return P2pTradeOffer::create([
                    'listing_id' => $listing->id,
                    'buyer_id' => $buyerId,
                    'seller_id' => $listing->seller_id,
                    'reference' => 'P2PO-' . strtoupper(Str::random(20)),
                    'amount_minor' => $amountMinor,
                    'price_minor' => $priceMinor,
                    'currency' => $listing->currency,
                    'status' => 'pending',
                    'idempotency_key' => $idempotencyKey,
                    'note' => $note,
                    'expires_at' => now()->addHours($expiryHours),
                    'metadata' => ['addon' => 'p2p.transfers'],
                ]);
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') || str_contains(strtolower($e->getMessage()), 'duplicate')) {
                $existing = P2pTradeOffer::where('buyer_id', $buyerId)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) return $existing;
            }
            throw $e;
        }
    }

    public function acceptOffer(int $sellerId, int $offerId): P2pTradeOffer
    {
        if (!Addon::query()->where('identifier', 'escrow.protection')->where('status', 'active')->exists()) {
            throw new RuntimeException('Escrow Protection must be active before accepting a P2P trade offer.');
        }

        return DB::transaction(function () use ($sellerId, $offerId) {
            $offer = P2pTradeOffer::with('listing')->lockForUpdate()->findOrFail($offerId);
            if ($offer->seller_id !== $sellerId) throw new RuntimeException('Only the listing owner can accept this offer.');
            if ($offer->status !== 'pending') throw new RuntimeException('This offer is not pending.');
            if ($offer->expires_at && $offer->expires_at->isPast()) throw new RuntimeException('This offer has expired.');

            if ($offer->price_minor === '0') throw new RuntimeException('Trade value must be greater than zero.');
            $seller = $offer->seller;
            if (!$seller) throw new RuntimeException('Seller account could not be resolved.');

            $escrow = app(EscrowService::class)->create(
                $offer->buyer_id,
                (string) ($seller->username ?: $seller->id),
                (string) $offer->price_minor,
                $offer->listing->asset_key,
                $offer->listing->description,
                'p2p-offer:' . $offer->id
            );

            $offer->status = 'accepted';
            $offer->escrow_transaction_id = $escrow->id;
            $offer->accepted_at = now();
            $offer->save();
            $offer->listing->update(['status' => 'matched']);

            return $offer->fresh();
        });
    }

    public function rejectOffer(int $sellerId, int $offerId): P2pTradeOffer
    {
        $offer = P2pTradeOffer::lockForUpdate()->findOrFail($offerId);
        if ($offer->seller_id !== $sellerId) throw new RuntimeException('Only the listing owner can reject this offer.');
        if ($offer->status !== 'pending') throw new RuntimeException('This offer is not pending.');
        $offer->status = 'rejected';
        $offer->rejected_at = now();
        $offer->save();
        return $offer->fresh();
    }

    public function cancelOffer(int $buyerId, int $offerId): P2pTradeOffer
    {
        $offer = P2pTradeOffer::lockForUpdate()->findOrFail($offerId);
        if ($offer->buyer_id !== $buyerId) throw new RuntimeException('Only the offer creator can cancel it.');
        if ($offer->status !== 'pending') throw new RuntimeException('Only pending offers can be cancelled.');
        $offer->status = 'cancelled';
        $offer->cancelled_at = now();
        $offer->save();
        return $offer->fresh();
    }

    public function expireOffers(): int
    {
        return P2pTradeOffer::where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    public function requeryOffer(int $offerId): P2pTradeOffer
    {
        $offer = P2pTradeOffer::with('listing')->findOrFail($offerId);
        if ($offer->escrow_transaction_id) {
            $escrow = EscrowTransaction::find($offer->escrow_transaction_id);
            if ($escrow && in_array($escrow->status, ['released','refunded','cancelled'], true)) {
                $offer->status = $escrow->status === 'released' ? 'completed' : 'cancelled';
                $offer->save();
            }
        }
        return $offer->fresh();
    }
}
