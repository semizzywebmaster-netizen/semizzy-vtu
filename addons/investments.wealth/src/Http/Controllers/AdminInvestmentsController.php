<?php
namespace Semizzy\Addons\Investments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Semizzy\Addons\Investments\Models\InvestmentAccount;
use Semizzy\Addons\Investments\Models\InvestmentMarketQuote;
use Semizzy\Addons\Investments\Models\InvestmentCorporateAction;
use Semizzy\Addons\Investments\Models\InvestmentOrder;
use Semizzy\Addons\Investments\Models\InvestmentProduct;
use Semizzy\Addons\Investments\Models\InvestmentProvider;
use Semizzy\Addons\Investments\Models\InvestmentSecurity;

class AdminInvestmentsController extends Controller
{
    public function index()
    {
        return inertia('Admin/Investments', [
            'products' => InvestmentProduct::latest()->get(),
            'investments' => InvestmentAccount::with('product')->latest()->paginate(25),
            'securities' => InvestmentSecurity::with(['quotes' => fn ($q) => $q->latest('observed_at')->limit(1)])->latest()->paginate(25, ['*'], 'securities_page'),
            'providers' => InvestmentProvider::latest()->get(),
            'orders' => InvestmentOrder::with(['security','provider'])->latest()->paginate(25, ['*'], 'orders_page'),
            'corporateActions' => InvestmentCorporateAction::with('security')->latest()->paginate(25, ['*'], 'corporate_actions_page'),
        ]);
    }

    public function toggle(Request $request, InvestmentProduct $product)
    {
        $product->active = ! $product->active;
        $product->saveOrFail();

        return back();
    }

    public function storeSecurity(Request $request)
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'asset_type' => ['required', Rule::in(['equity', 'etf', 'bond', 'fund', 'other'])],
            'market' => ['nullable', 'string', 'max:80'],
            'exchange' => ['nullable', 'string', 'max:80'],
            'isin' => ['nullable', 'string', 'max:32'],
            'currency' => ['required', 'string', 'size:3'],
            'country' => ['nullable', 'string', 'size:2'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        InvestmentSecurity::updateOrCreate(
            ['symbol' => $data['symbol'], 'market' => $data['market'] ?? null],
            [...$data, 'status' => 'draft']
        );

        return back()->with('success', 'Security saved as draft.');
    }

    public function publishSecurity(InvestmentSecurity $security)
    {
        $security->update(['status' => 'published', 'published_at' => now()]);

        return back()->with('success', 'Security published.');
    }

    public function unpublishSecurity(InvestmentSecurity $security)
    {
        $security->update(['status' => 'draft', 'published_at' => null]);

        return back()->with('success', 'Security unpublished.');
    }

    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'alpha_dash'],
            'provider_type' => ['required', Rule::in(['broker', 'market_data', 'both', 'custodian', 'other'])],
            'regulatory_note' => ['nullable', 'string'],
            'capabilities' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'is_data_provider' => ['boolean'],
            'is_execution_provider' => ['boolean'],
        ]);

        InvestmentProvider::updateOrCreate(
            ['key' => $data['key']],
            [
                ...$data,
                'status' => 'disabled',
                'verified' => false,
            ]
        );

        return back()->with('success', 'Provider saved disabled and unverified.');
    }

    public function verifyProvider(InvestmentProvider $provider)
    {
        $provider->update([
            'verified' => true,
            'status' => 'enabled',
        ]);

        return back()->with('success', 'Provider verified and enabled.');
    }

    public function disableProvider(InvestmentProvider $provider)
    {
        $provider->update(['status' => 'disabled']);

        return back()->with('success', 'Provider disabled.');
    }

    public function storeCorporateAction(Request $request)
    {
        $data = $request->validate([
            'security_id' => ['required', 'integer', 'exists:investment_securities,id'],
            'action_type' => ['required', Rule::in(['dividend','bonus','split','rights','merger','other'])],
            'reference' => ['required', 'string', 'max:255'],
            'record_date' => ['nullable', 'date'],
            'ex_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['announced','confirmed','processed'])],
            'metadata' => ['nullable', 'array'],
        ]);

        InvestmentCorporateAction::updateOrCreate(
            ['reference' => $data['reference']],
            $data
        );

        return back()->with('success', 'Corporate action saved with its source/reference data.');
    }

    public function storeQuote(Request $request, InvestmentSecurity $security)
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:120'],
            'bid' => ['nullable', 'numeric', 'min:0'],
            'ask' => ['nullable', 'numeric', 'min:0'],
            'last_price' => ['nullable', 'numeric', 'min:0'],
            'open_price' => ['nullable', 'numeric', 'min:0'],
            'high_price' => ['nullable', 'numeric', 'min:0'],
            'low_price' => ['nullable', 'numeric', 'min:0'],
            'volume' => ['nullable', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'observed_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:observed_at'],
            'raw_metadata' => ['nullable', 'array'],
        ]);

        InvestmentMarketQuote::create([
            ...$data,
            'security_id' => $security->id,
        ]);

        return back()->with('success', 'Quote snapshot recorded with its source and timestamp.');
    }
}
