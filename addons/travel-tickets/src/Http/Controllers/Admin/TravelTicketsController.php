<?php

namespace Semizzy\Addons\TravelTickets\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Semizzy\Addons\TravelTickets\Models\TravelBooking;
use Semizzy\Addons\TravelTickets\Models\TravelRefund;
use Semizzy\Addons\TravelTickets\Models\TravelService;
use Semizzy\Addons\TravelTickets\Services\TravelBookingQueryService;
use Semizzy\Addons\TravelTickets\Services\TravelRefundService;

class TravelTicketsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/TravelTickets', [
            'services' => TravelService::orderBy('type')->get(),
            'bookings' => TravelBooking::latest()->paginate(30),
            'refunds' => TravelRefund::with('booking')->latest()->paginate(30, ['*'], 'refund_page'),
        ]);
    }

    public function storeService(Request $request)
    {
        $d = $request->validate([
            'type' => 'required|in:flight,bus,hotel',
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:100|unique:travel_services,code',
            'description' => 'nullable|string',
            'enabled' => 'boolean',
            'requirements' => 'nullable|array',
        ]);
        TravelService::create($d);
        return back()->with('success', 'Travel service created.');
    }

    public function toggleService(TravelService $service)
    {
        $service->update(['enabled' => ! $service->enabled]);
        return back()->with('success', 'Travel service status updated.');
    }

    public function requeryBooking(TravelBooking $booking, TravelBookingQueryService $query)
    {
        if ($booking->status !== 'provider_pending') {
            return back()->with('error', 'Only provider-pending bookings can be requeried.');
        }

        $query->requery($booking);

        return back()->with('success', 'Travel booking requery completed.');
    }

    public function requestRefund(Request $request, TravelRefundService $refunds, TravelBooking $booking)
    {
        $d = $request->validate(['note' => 'nullable|string|max:1000']);
        $refunds->request($booking, $d['note'] ?? null);
        return back()->with('success', 'Refund request created.');
    }

    public function approveRefund(Request $request, TravelRefundService $refunds, TravelRefund $refund)
    {
        $d = $request->validate(['note' => 'nullable|string|max:1000']);
        $refunds->approve($refund, $request->user(), $d['note'] ?? null);
        return back()->with('success', 'Refund approved and wallet credited.');
    }
}
