<?php

namespace Semizzy\Addons\ForexDigitalAssets\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetInstrument;
use Semizzy\Addons\ForexDigitalAssets\Models\ForexDigitalAssetProvider;
use Semizzy\Addons\ForexDigitalAssets\Services\MarketDataDriverResolver;

class AdminForexDigitalAssetsController extends Controller
{
    public function index()
    {
        return inertia('Admin/ForexDigitalAssets', [
            'providers' => ForexDigitalAssetProvider::latest()->get(),
            'instruments' => ForexDigitalAssetInstrument::latest()->paginate(25),
        ]);
    }

    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash'],
            'driver' => ['nullable', 'string', 'max:100'],
            'capabilities' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'is_market_data_provider' => ['boolean'],
            'is_execution_provider' => ['boolean'],
            'priority' => ['nullable', 'integer', 'min:1'],
        ]);

        ForexDigitalAssetProvider::updateOrCreate(
            ['code' => $data['code']],
            [...$data, 'enabled' => false, 'verified' => false]
        );

        return back()->with('success', 'Provider saved disabled and unverified.');
    }

    public function verifyProvider(ForexDigitalAssetProvider $provider)
    {
        if (! $provider->is_market_data_provider && ! $provider->is_execution_provider) {
            return back()->withErrors(['provider' => 'Provider must have a declared market-data or execution capability.']);
        }

        $provider->update([
            'verified' => true,
            'enabled' => $provider->is_market_data_provider,
        ]);

        return back()->with('success', $provider->is_market_data_provider
            ? 'Market-data provider verified and enabled.'
            : 'Execution provider verified; it remains disabled until execution is explicitly enabled.');
    }

    public function testProvider(ForexDigitalAssetProvider $provider, MarketDataDriverResolver $drivers)
    {
        if (! $provider->verified || ! $provider->is_market_data_provider) {
            return back()->withErrors(['provider' => 'Provider must be verified and marked as a market-data provider before testing.']);
        }

        try {
            $driver = $drivers->resolve($provider);
            $count = (int) $driver->refreshQuotes($provider);

            $provider->update([
                'last_health_check_at' => now(),
                'last_success_at' => now(),
                'last_error' => null,
            ]);

            return back()->with('success', "Provider test succeeded; {$count} quote(s) refreshed.");
        } catch (\\Throwable $e) {
            $provider->update([
                'last_health_check_at' => now(),
                'last_failure_at' => now(),
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            return back()->withErrors(['provider' => 'Provider test failed: '.mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    public function disableProvider(ForexDigitalAssetProvider $provider)
    {
        $provider->update(['enabled' => false]);
        return back()->with('success', 'Provider disabled.');
    }

    public function storeInstrument(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(['forex', 'digital_asset'])],
            'symbol' => ['required', 'string', 'max:32'],
            'base_code' => ['required', 'string', 'max:20'],
            'quote_code' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'instrument_type' => ['required', 'string', 'max:40'],
            'source_name' => ['required', 'string', 'max:255'],
            'source_reference' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'source_checked_at' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        ForexDigitalAssetInstrument::updateOrCreate(
            ['category' => $data['category'], 'symbol' => $data['symbol']],
            [...$data, 'status' => 'draft', 'enabled' => false, 'verified' => false]
        );

        return back()->with('success', 'Instrument saved as an unverified draft.');
    }

    public function verifyInstrument(ForexDigitalAssetInstrument $instrument)
    {
        if (! $instrument->hasRequiredProvenance()) {
            return back()->withErrors(['instrument' => 'Complete source provenance before verification.']);
        }

        $instrument->update(['verified' => true]);
        return back()->with('success', 'Instrument provenance verified.');
    }

    public function publishInstrument(ForexDigitalAssetInstrument $instrument)
    {
        if (! $instrument->verified || ! $instrument->hasRequiredProvenance()) {
            return back()->withErrors(['instrument' => 'Instrument must have verified provenance before publication.']);
        }

        $instrument->update([
            'status' => 'published',
            'enabled' => true,
            'published_at' => now(),
        ]);

        return back()->with('success', 'Instrument published.');
    }

    public function unpublishInstrument(ForexDigitalAssetInstrument $instrument)
    {
        $instrument->update([
            'status' => 'draft',
            'enabled' => false,
            'published_at' => null,
        ]);

        return back()->with('success', 'Instrument unpublished.');
    }
}
