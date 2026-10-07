<?php

namespace Semizzy\Addons\P2p\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\P2p\Models\P2pTradeListing;
use Semizzy\Addons\P2p\Models\P2pTradeOffer;
use Semizzy\Addons\P2p\Services\P2pTradingService;

final class P2pTradingController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('P2p/Trading', [
            'listings' => P2pTradeListing::with('seller:id,name,username')->where('status','open')->latest()->paginate(30),
            'myOffers' => P2pTradeOffer::with('listing')->where('buyer_id',$request->user()->id)->latest()->paginate(20),
        ]);
    }

    public function createListing(Request $request, P2pTradingService $service)
    {
        $data = $request->validate([
            'side' => ['required','in:buy,sell'],
            'asset_key' => ['required','string','max:100'],
            'amount_minor' => ['required','regex:/^\d+$/'],
            'price_minor' => ['required','regex:/^\d+$/'],
            'description' => ['nullable','string','max:2000'],
        ]);

        $listing = $service->createListing($request->user()->id, $data['side'], $data['asset_key'], $data['amount_minor'], $data['price_minor'], $data['description'] ?? null);
        return back()->with('success', 'P2P trading listing created: ' . $listing->id);
    }

    public function offer(Request $request, P2pTradingService $service)
    {
        $data = $request->validate([
            'listing_id' => ['required','integer','exists:p2p_trade_listings,id'],
            'amount_minor' => ['required','regex:/^\d+$/'],
            'price_minor' => ['required','regex:/^\d+$/'],
            'note' => ['nullable','string','max:1000'],
            'idempotency_key' => ['nullable','string','max:120'],
        ]);

        $offer = $service->createOffer($request->user()->id, (int)$data['listing_id'], $data['amount_minor'], $data['price_minor'], $data['note'] ?? null, $data['idempotency_key'] ?? Str::uuid()->toString());
        return back()->with('success', 'Offer created: ' . $offer->reference);
    }

    public function accept(Request $request, P2pTradingService $service, int $offer)
    {
        $result = $service->acceptOffer($request->user()->id, $offer);
        return back()->with('success', 'Offer accepted and funded through Escrow: ' . $result->reference);
    }

    public function reject(Request $request, P2pTradingService $service, int $offer)
    {
        $service->rejectOffer($request->user()->id, $offer);
        return back()->with('success', 'Offer rejected.');
    }

    public function cancel(Request $request, P2pTradingService $service, int $offer)
    {
        $service->cancelOffer($request->user()->id, $offer);
        return back()->with('success', 'Offer cancelled.');
    }

    public function requery(Request $request, P2pTradingService $service, int $offer)
    {
        return response()->json(['data' => $service->requeryOffer($offer)]);
    }
}
