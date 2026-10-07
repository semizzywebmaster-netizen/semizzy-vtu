<?php

namespace Semizzy\Addons\TravelTickets\Services;

use Semizzy\Addons\TravelTickets\Models\TravelBooking;
use Semizzy\Addons\TravelTickets\Models\TravelBookingAttempt;

class TravelCancellationService
{
    public function __construct(
        private TravelProviderGateway $gateway,
        private TravelWalletService $wallet,
    ) {
    }

    public function cancel(TravelBooking $booking): TravelBooking
    {
        $booking = $booking->fresh();

        if (!$booking || $booking->status !== 'confirmed') {
            throw new \RuntimeException('Only confirmed travel bookings can be cancelled.');
        }

        $type = strtolower((string) $booking->type);
        $operation = $type . '_cancel';
        $payload = [
            'booking_id' => $booking->id,
            'provider_reference' => $booking->provider_reference,
            'booking_reference' => $booking->booking_reference,
            'provider_code' => $booking->provider_code,
        ];

        try {
            $result = $this->gateway->requestBooking($type . '_cancel', $operation, $payload);
        } catch (\Throwable $e) {
            TravelBookingAttempt::create([
                'travel_booking_id' => $booking->id,
                'provider_code' => $booking->provider_code,
                'operation' => $operation,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if (($result['status'] ?? null) === 'ambiguous') {
            TravelBookingAttempt::create([
                'travel_booking_id' => $booking->id,
                'provider_code' => $booking->provider_code,
                'operation' => $operation,
                'status' => 'ambiguous',
                'error' => $result['message'] ?? 'Cancellation outcome is inconclusive.',
                'response' => $result['response'] ?? null,
            ]);

            throw new \RuntimeException('Cancellation outcome is inconclusive; do not retry automatically.');
        }

        $response = is_array($result['response'] ?? null) ? $result['response'] : [];

        TravelBookingAttempt::create([
            'travel_booking_id' => $booking->id,
            'provider_code' => $booking->provider_code,
            'operation' => $operation,
            'status' => 'accepted',
            'response' => $response,
        ]);

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'booking_data' => array_merge($booking->booking_data ?? [], ['cancellation_response' => $response]),
            'failure_reason' => null,
        ]);

        return $booking->fresh();
    }
}
