<?php

namespace Semizzy\Addons\ForexDigitalAssets\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetInstrument;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetQuote;

class ForexMarketController extends Controller
{
    public function index()
    {
        return inertia('ForexDigitalAssets/Market', [
            'instruments' => $this->publishedInstruments(),
        ]);
    }

    public function instruments(): JsonResponse
    {
        return response()->json([
            'data' => $this->publishedInstruments()->values(),
        ]);
    }

    public function quotes(ForexDigitalAssetInstrument $instrument): JsonResponse
    {
        if (! $instrument->enabled || ! $instrument->verified || $instrument->status !== 'published') {
            return response()->json(['message' => 'Instrument is unavailable.'], 404);
        }

        $quotes = ForexDigitalAssetQuote::query()
            ->where('instrument_id', $instrument->id)
            ->whereHas('provider', fn ($q) => $q
                ->where('enabled', true)
                ->where('verified', true)
                ->where('is_market_data_provider', true)
                ->where('paused', false)
                ->where('maintenance', false))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('observed_at')
            ->limit(20)
            ->get();

        return response()->json([
            'instrument' => $instrument,
            'data' => $quotes,
            'fresh' => $quotes->isNotEmpty(),
        ]);
    }

    private function publishedInstruments()
    {
        return ForexDigitalAssetInstrument::query()
            ->where('enabled', true)
            ->where('verified', true)
            ->where('status', 'published')
            ->orderBy('category')
            ->orderBy('symbol')
            ->get();
    }
}
