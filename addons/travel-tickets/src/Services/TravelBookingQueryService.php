<?php

namespace Semizzy\Addons\TravelTickets\Services;

use Semizzy\Addons\TravelTickets\Models\TravelBooking;
use Semizzy\Addons\TravelTickets\Models\TravelBookingAttempt;

class TravelBookingQueryService
{
    private const CONFIRMED = ['confirmed', 'success', 'successful', 'booked', 'ticketed', 'completed', 'complete'];
    private const FAILED = ['failed', 'rejected', 'declined', 'error', 'cancelled', 'canceled'];

    public function __construct(private TravelProviderGateway $gateway, private TravelWalletService $wallet) {}

    public function requery(TravelBooking $booking): TravelBooking
    {
        $booking = $booking->fresh();
        if (!$booking || $booking->status !== 'provider_pending') return $booking;

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

        $response = $this->responseArray($result['response'] ?? null);
        $provider = $result['provider'] ?? null;
        $providerCode = $provider?->code ?? $provider?->identifier ?? $booking->provider_code;
        $status = $this->normalizeStatus($response);
        $providerReference = $this->reference($response) ?: $booking->provider_reference;
        $bookingReference = $this->bookingReference($response) ?: $booking->booking_reference;

        TravelBookingAttempt::create([
            'travel_booking_id' => $booking->id,
            'provider_code' => $providerCode,
            'operation' => $operation,
            'status' => $this->attemptStatus($status),
            'provider_reference' => $providerReference,
            'response' => $response,
        ]);

        if (in_array($status, self::CONFIRMED, true)) {
            try {
                $this->wallet->settle($booking);
            } catch (\Throwable $e) {
                $booking->update([
                    'provider_code' => $providerCode,
                    'provider_reference' => $providerReference,
                    'booking_reference' => $bookingReference,
                    'booking_data' => array_merge($booking->booking_data ?? [], ['requery_response' => $response]),
                    'failure_reason' => 'Provider confirmed booking but wallet settlement is pending: ' . $e->getMessage(),
                ]);
                return $booking->fresh();
            }

            $booking->update([
                'status' => 'confirmed',
                'provider_code' => $providerCode,
                'provider_reference' => $providerReference,
                'booking_reference' => $bookingReference,
                'booking_data' => array_merge($booking->booking_data ?? [], ['requery_response' => $response]),
                'confirmed_at' => now(),
                'failure_reason' => null,
            ]);
            return $booking->fresh();
        }

        if (in_array($status, self::FAILED, true)) {
            $this->wallet->release($booking);
            $booking->update([
                'status' => 'failed',
                'provider_code' => $providerCode,
                'provider_reference' => $providerReference,
                'failure_reason' => 'Provider requery reported a failed booking.',
            ]);
            return $booking->fresh();
        }

        $booking->update([
            'provider_code' => $providerCode,
            'provider_reference' => $providerReference,
            'booking_reference' => $bookingReference,
            'booking_data' => array_merge($booking->booking_data ?? [], ['requery_response' => $response]),
            'failure_reason' => 'Provider booking status remains pending.',
        ]);
        return $booking->fresh();
    }

    private function responseArray(mixed $response): array
    {
        if (!is_array($response)) return [];
        if (isset($response['data']) && is_array($response['data'])) {
            $nested = $response['data'];
            foreach (['status', 'booking_status', 'bookingStatus', 'provider_reference', 'providerReference', 'reference', 'booking_reference', 'bookingReference', 'pnr'] as $key) {
                if (array_key_exists($key, $nested) && !array_key_exists($key, $response)) {
                    $response[$key] = $nested[$key];
                }
            }
        }
        return $response;
    }

    private function normalizeStatus(array $response): string
    {
        $status = $response['status'] ?? $response['booking_status'] ?? $response['bookingStatus'] ?? $response['state'] ?? 'pending';
        $status = strtolower(trim((string) $status));
        return $status !== '' ? $status : 'pending';
    }

    private function attemptStatus(string $status): string
    {
        return in_array($status, self::CONFIRMED, true)
            ? 'accepted'
            : (in_array($status, self::FAILED, true) ? 'failed' : 'pending');
    }

    private function reference(array $response): ?string
    {
        foreach (['provider_reference', 'providerReference', 'reference', 'transaction_id', 'transactionId', 'id'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) return (string) $response[$key];
        }
        return null;
    }

    private function bookingReference(array $response): ?string
    {
        foreach (['booking_reference', 'bookingReference', 'booking_ref', 'pnr', 'confirmation_code', 'confirmationCode'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) return (string) $response[$key];
        }
        return null;
    }
}
