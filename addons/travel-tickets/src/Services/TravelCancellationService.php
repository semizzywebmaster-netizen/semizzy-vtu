<?php

namespace Semizzy\Addons\TravelTickets\Services;

use Semizzy\Addons\TravelTickets\Models\TravelBooking;
use Semizzy\Addons\TravelTickets\Models\TravelBookingAttempt;

class TravelCancellationService
{
    public function __construct(private TravelProviderGateway $gateway) {}

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

        $provider = $result['provider'] ?? null;
        $providerCode = $provider?->code ?? $provider?->identifier ?? $booking->provider_code;

        if (($result['status'] ?? null) === 'ambiguous') {
            TravelBookingAttempt::create([
                'travel_booking_id' => $booking->id,
                'provider_code' => $providerCode,
                'operation' => $operation,
                'status' => 'ambiguous',
                'error' => $result['message'] ?? 'Cancellation outcome is inconclusive.',
                'response' => $result['response'] ?? null,
            ]);

            throw new \RuntimeException('Cancellation outcome is inconclusive; do not retry automatically.');
        }

        $response = is_array($result['response'] ?? null) ? $result['response'] : [];
        $cancellationReference = $this->reference($response);

        TravelBookingAttempt::create([
            'travel_booking_id' => $booking->id,
            'provider_code' => $providerCode,
            'operation' => $operation,
            'status' => 'accepted',
            'provider_reference' => $cancellationReference,
            'response' => $response,
        ]);

        $bookingData = $booking->booking_data ?? [];
        $bookingData['cancellation_response'] = $response;
        if ($cancellationReference !== null) {
            $bookingData['cancellation_reference'] = $cancellationReference;
        }

        $booking->update([
            'status' => 'cancelled',
            'provider_code' => $providerCode,
            'cancelled_at' => now(),
            'booking_data' => $bookingData,
            'failure_reason' => null,
        ]);

        return $booking->fresh();
    }

    private function reference(array $response): ?string
    {
        foreach ([
            'cancellation_reference',
            'cancellationReference',
            'cancel_reference',
            'cancelReference',
            'provider_reference',
            'providerReference',
            'reference',
            'transaction_id',
            'transactionId',
            'id',
        ] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                return (string) $response[$key];
            }
        }

        return null;
    }
}
