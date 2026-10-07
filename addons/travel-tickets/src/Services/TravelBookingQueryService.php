<?php

namespace Semizzy\Addons\TravelTickets\Services;

use Semizzy\Addons\TravelTickets\Models\TravelBooking;
use Semizzy\Addons\TravelTickets\Models\TravelBookingAttempt;

class TravelBookingQueryService
{
    public function __construct(
        private TravelProviderGateway $gateway,
        private TravelWalletService $wallet,
    ) {
    }

    public function requery(TravelBooking $booking): TravelBooking
    {
        $booking = $booking->fresh();

        if (!$booking || $booking->status !== 'provider_pending') {
            return $booking;
        }

        $type = strtolower((string) $booking->type);
        $operation = $type . '_requery';
        $payload = [
            'booking_id' => $booking->id,
            'provider_reference' => $booking->provider_reference,
            'booking_reference' => $booking->booking_reference,
            'provider_code' => $booking->provider_code,
            'currency' => $booking->currency,
        ];

        try {
            $result = $this->gateway->request($type . '_requery', $operation, $payload);
        } catch (\Throwable $e) {
            TravelBookingAttempt::create([
                'travel_booking_id' => $booking->id,
                'provider_code' => $booking->provider_code,
                'operation' => $operation,
                'status' => 'pending',
                'error' => $e->getMessage(),
            ]);

            return $booking->fresh();
        }

        $response = is_array($result['response'] ?? null) ? $result['response'] : [];
        $status = strtolower((string) ($response['status'] ?? $response['booking_status'] ?? $response['bookingStatus'] ?? 'pending'));
        $providerReference = $this->reference($response) ?: $booking->provider_reference;
        $bookingReference = $this->bookingReference($response) ?: $booking->booking_reference;

        TravelBookingAttempt::create([
            'travel_booking_id' => $booking->id,
            'provider_code' => $booking->provider_code,
            'operation' => $operation,
            'status' => $this->attemptStatus($status),
            'provider_reference' => $providerReference,
            'response' => $response,
        ]);

        if (in_array($status, ['confirmed', 'success', 'successful', 'booked', 'ticketed'], true)) {
            $booking->update([
                'status' => 'confirmed',
                'provider_reference' => $providerReference,
                'booking_reference' => $bookingReference,
                'booking_data' => array_merge($booking->booking_data ?? [], ['requery_response' => $response]),
                'confirmed_at' => now(),
                'failure_reason' => null,
            ]);

            $this->wallet->settle($booking);

            return $booking->fresh();
        }

        if (in_array($status, ['failed', 'rejected', 'declined', 'error'], true)) {
            $this->wallet->release($booking);

            $booking->update([
                'status' => 'failed',
                'provider_reference' => $providerReference,
                'failure_reason' => 'Provider requery reported a failed booking.',
            ]);

            return $booking->fresh();
        }

        $booking->update([
            'provider_reference' => $providerReference,
            'booking_reference' => $bookingReference,
            'booking_data' => array_merge($booking->booking_data ?? [], ['requery_response' => $response]),
            'failure_reason' => 'Provider booking status remains pending.',
        ]);

        return $booking->fresh();
    }

    private function attemptStatus(string $status): string
    {
        return in_array($status, ['confirmed', 'success', 'successful', 'booked', 'ticketed'], true)
            ? 'accepted'
            : ($status === 'pending' ? 'pending' : 'failed');
    }

    private function reference(array $response): ?string
    {
        foreach (['provider_reference', 'providerReference', 'reference', 'transaction_id', 'transactionId', 'id'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                return (string) $response[$key];
            }
        }

        return null;
    }

    private function bookingReference(array $response): ?string
    {
        foreach (['booking_reference', 'bookingReference', 'booking_ref', 'pnr', 'confirmation_code', 'confirmationCode'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                return (string) $response[$key];
            }
        }

        return null;
    }
}
