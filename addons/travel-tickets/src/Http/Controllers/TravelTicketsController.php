<?php

namespace Semizzy\Addons\TravelTickets\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Semizzy\Addons\TravelTickets\Models\TravelBooking;
use Semizzy\Addons\TravelTickets\Models\TravelService;
use Semizzy\Addons\TravelTickets\Services\TravelBookingExecutionService;
use Semizzy\Addons\TravelTickets\Services\TravelBookingQueryService;
use Semizzy\Addons\TravelTickets\Services\TravelBookingService;
use Semizzy\Addons\TravelTickets\Services\TravelCancellationService;

class TravelTicketsController extends Controller
{
    public function index()
    {
        return Inertia::render('TravelTickets/Index', [
            'services' => TravelService::where('enabled', true)->orderBy('type')->get(),
        ]);
    }

    public function apiServices()
    {
        return response()->json([
            'services' => TravelService::where('enabled', true)->orderBy('type')->get(),
        ]);
    }

    public function bookings(Request $request)
    {
        return Inertia::render('TravelTickets/Bookings', [
            'bookings' => TravelBooking::where('user_id', $request->user()->id)->latest()->paginate(20),
        ]);
    }

    public function store(
        Request $request,
        TravelBookingService $service,
        TravelBookingExecutionService $execution,
    ) {
        $d = $request->validate([
            'service_id' => 'required|integer',
            'type' => 'required|string|in:flight,bus,hotel',
            'amount' => 'required|numeric|min:0.01',
            'fee' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'passengers' => 'nullable|array',
            'search_data' => 'nullable|array',
            'booking_data' => 'nullable|array',
            'idempotency_key' => 'required|string|max:100',
        ]);

        $booking = $service->createBooking(
            $request->user()->id,
            (int) $d['service_id'],
            $d['type'],
            $d,
            $d['idempotency_key']
        );

        return response()->json([
            'booking' => $execution->execute($booking),
        ], 201);
    }

    public function requery(
        Request $request,
        TravelBookingQueryService $query,
        int $booking,
    ) {
        $bookingModel = TravelBooking::where('id', $booking)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'booking' => $query->requery($bookingModel),
        ]);
    }

    public function cancel(
        Request $request,
        TravelCancellationService $cancellation,
        int $booking,
    ) {
        $bookingModel = TravelBooking::where('id', $booking)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'booking' => $cancellation->cancel($bookingModel),
        ]);
    }
}