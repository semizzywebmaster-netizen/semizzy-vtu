<?php

namespace Semizzy\Addons\ForexDigitalAssets\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetInstrument;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetProvider;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetQuote;

class ForexQuoteIngestionService
{
    /**
     * Persist a real provider quote. This service never creates synthetic prices.
     */
    public function ingest(
        ForexDigitalAssetProvider $provider,
        ForexDigitalAssetInstrument $instrument,
        array $quote
    ): ForexDigitalAssetQuote {
        if (! $provider->canProvideMarketData()) {
            throw new RuntimeException('Provider is not enabled and verified for market data.');
        }

        if (! $instrument->enabled || ! $instrument->verified || $instrument->status !== 'published') {
            throw new RuntimeException('Instrument is not published and verified.');
        }

        if (($quote['source_reference'] ?? null) === null) {
            throw new RuntimeException('A provider source reference is required.');
        }

        $observedAt = $quote['observed_at'] ?? now();
        $expiresAt = $quote['expires_at'] ?? now()->addSeconds(
            (int) ($provider->settings['stale_quote_seconds'] ?? 120)
        );

        if ($expiresAt <= $observedAt) {
            throw new RuntimeException('Quote expiry must be after observation time.');
        }

        $mid = $quote['mid'] ?? null;
        if ($mid === null && isset($quote['bid'], $quote['ask'])) {
            $mid = bcdiv(
                bcadd((string) $quote['bid'], (string) $quote['ask'], 12),
                '2',
                12
            );
        }

        return DB::transaction(function () use ($provider, $instrument, $quote, $observedAt, $expiresAt, $mid) {
            $record = ForexDigitalAssetQuote::create([
                'instrument_id' => $instrument->id,
                'provider_id' => $provider->id,
                'bid' => Arr::get($quote, 'bid'),
                'ask' => Arr::get($quote, 'ask'),
                'mid' => $mid,
                'open' => Arr::get($quote, 'open'),
                'high' => Arr::get($quote, 'high'),
                'low' => Arr::get($quote, 'low'),
                'close' => Arr::get($quote, 'close'),
                'volume' => Arr::get($quote, 'volume'),
                'source_reference' => $quote['source_reference'],
                'observed_at' => $observedAt,
                'expires_at' => $expiresAt,
            ]);

            $provider->update([
                'last_success_at' => now(),
                'last_error' => null,
            ]);

            return $record;
        });
    }

    public function recordFailure(ForexDigitalAssetProvider $provider, string $message): void
    {
        $provider->update([
            'last_failure_at' => now(),
            'last_error' => mb_substr($message, 0, 2000),
        ]);
    }
}
