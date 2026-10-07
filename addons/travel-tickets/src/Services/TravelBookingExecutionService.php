<?php

namespace Semizzy\Addons\TravelTickets\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\TravelTickets\Models\TravelBooking;
use Semizzy\Addons\TravelTickets\Models\TravelBookingAttempt;

class TravelBookingExecutionService
{
    public function __construct(
        private TravelProviderGateway $gateway,
        private TravelWalletService $wallet,
    ) {
    }

    public function execute(TravelBooking $booking): TravelBooking
    {
        $claimed = DB::transaction(function () use ($booking): TravelBooking {
            $current = TravelBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (in_array($current->status, ['confirmed', 'cancelled', 'failed'], true)) {
                return $current;
            }

            if ($current->status === 'provider_pending') {
                return $current;
            }

            $current->update(['status' => 'processing', 'failure_reason' => null]);

            return $current->fresh();
        });

        if ($claimed->status !== 'processing') {
            return $claimed;
        }

        try {
            $this->wallet->reserve($claimed);
        } catch (\Throwable $e) {
            return $this->markFailed($claimed, $e->getMessage());
        }

        $type = strtolower((string) $claimed->type);
        $capability = $type . '_book';
        $operation = $type . '_book';
        $payload = [
            'booking_id' => $claimed->id,
            'currency' => $claimed->currency,
            'amount' => (string) $claimed->amount,
            'fee' => (string) $claimed->fee,
            'total' => (string) $claimed->total,
            'search_data' => $claimed->search_data,
            'passengers' => $claimed->passengers,
            'booking_data' => $claimed->booking_data,
        ];

        try {
            $result = $this->gateway->requestBooking($capability, $operation, $payload);
        } catch (\Throwable $e) {
            $this->wallet->release($claimed);
            return $this->markFailed($claimed, $e->getMessage());
        }

        $provider = $result['provider'] ?? null;
        $providerCode = $provider?->code ?? $provider?->identifier ?? null;

        if ($result['status'] === 'ambiguous') {
            TravelBookingAttempt::create([
                'travel_booking_id' => $claimed->id,
                'provider_code' => $providerCode,
                'operation' => $operation,
                'status' => 'ambiguous',
                'provider_reference' => $this->reference($result['response'] ?? []),
                'error' => $result['message'] ?? 'Provider response is inconclusive.',
                'response' => $result['response'] ?? null,
            ]);

            $claimed->update([
                'status' => 'provider_pending',
                'provider_code' => $providerCode,
                'provider_reference' => $this->reference($result['response'] ?? []),
                'failure_reason' => $result['message'] ?? 'Provider response is inconclusive.',
            ]);

            return $claimed->fresh();
        }

        TravelBookingAttempt::create([
            'travel_booking_id' => $claimed->id,
            'provider_code' => $providerCode,
            'operation' => $operation,
            'status' => 'accepted',
            'provider_reference' => $this->reference($result['response'] ?? []),
            'response' => $result['response'] ?? null,
        ]);

        $response = is_array($result['response'] ?? null) ? $result['response'] : [];
        $providerReference = $this->reference($response);
        $bookingReference = $this->bookingReference($response);

        $claimed->update([
            'provider_code' => $providerCode,
            'provider_reference' => $providerReference,
        ]);

        try {
            $this->wallet->settle($claimed);
        } catch (\\Throwable $e) {
            TravelBookingAttempt::create([
                'travel_booking_id' => $claimed->id,
                'provider_code' => $providerCode,
                'operation' => $operation,
                'status' => 'accepted',
                'provider_reference' => $providerReference,
                'error' => 'Provider accepted booking but wallet settlement is pending: ' . $e->getMessage(),
                'response' => $response,
            ]);

            $claimed->update([
                'status' => 'provider_pending',
                'provider_code' => $providerCode,
                'provider_reference' => $providerReference,
                'booking_reference' => $bookingReference,
                'booking_data' => array_merge($claimed->booking_data ?? [], ['provider_response' => $response]),
                'failure_reason' => 'Provider accepted booking but wallet settlement is pending.',
            ]);

            return $claimed->fresh();
        }

        $claimed->update([
            'status' => 'confirmed',
            'booking_reference' => $bookingReference,
            'booking_data' => array_merge($claimed->booking_data ?? [], ['provider_response' => $response]),
            'confirmed_at' => now(),
        ]);

        return $claimed->fresh();
    }

    private function markFailed(TravelBooking $booking, string $reason): TravelBooking
    {
        TravelBookingAttempt::create([
            'travel_booking_id' => $booking->id,
            'provider_code' => null,
            'operation' => strtolower((string) $booking->type) . '_book',
            'status' => 'failed',
            'error' => $reason,
        ]);

        $booking->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);

        return $booking->fresh();
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
