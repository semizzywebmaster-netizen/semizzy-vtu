<?php

namespace Semizzy\Addons\ForexDigitalAssets\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetInstrument;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetProvider;
use Semizzy\Addons\ForexDigitalAssets\Services\ForexQuoteIngestionService;

class AdminForexQuoteController extends Controller
{
    public function store(
        Request $request,
        ForexDigitalAssetProvider $provider,
        ForexDigitalAssetInstrument $instrument,
        ForexQuoteIngestionService $ingestion
    ) {
        $data = $request->validate([
            'bid' => ['nullable', 'numeric', 'min:0'],
            'ask' => ['nullable', 'numeric', 'min:0'],
            'mid' => ['nullable', 'numeric', 'min:0'],
            'open' => ['nullable', 'numeric', 'min:0'],
            'high' => ['nullable', 'numeric', 'min:0'],
            'low' => ['nullable', 'numeric', 'min:0'],
            'close' => ['nullable', 'numeric', 'min:0'],
            'volume' => ['nullable', 'numeric', 'min:0'],
            'source_reference' => ['required', 'string', 'max:255'],
            'observed_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:observed_at'],
        ]);

        if (!isset($data['mid']) && (!isset($data['bid']) || !isset($data['ask']))) {
            return back()->withErrors(['quote' => 'Provide mid, or both bid and ask.']);
        }

        try {
            $ingestion->ingest($provider, $instrument, $data);
        } catch (\Throwable $e) {
            $ingestion->recordFailure($provider, $e->getMessage());
            return back()->withErrors(['quote' => $e->getMessage()]);
        }

        return back()->with('success', 'Verified provider quote recorded.');
    }
}
