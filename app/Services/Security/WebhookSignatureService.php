<?php

namespace App\Services\Security;

use Illuminate\Validation\ValidationException;

class WebhookSignatureService
{
    public function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return $timestamp.'.'.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    public function verify(
        string $payload,
        string $signature,
        string $secret,
        int $toleranceSeconds = 300,
        ?int $now = null
    ): bool {
        if ($secret === '' || $signature === '') {
            return false;
        }

        [$timestamp, $digest] = array_pad(explode('.', $signature, 2), 2, null);

        if (!ctype_digit((string) $timestamp) || !is_string($digest) || $digest === '') {
            return false;
        }

        $now ??= time();

        if (abs($now - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $digest);
    }

    public function assertValid(
        string $payload,
        string $signature,
        string $secret,
        int $toleranceSeconds = 300,
        ?int $now = null
    ): void {
        if (! $this->verify($payload, $signature, $secret, $toleranceSeconds, $now)) {
            throw ValidationException::withMessages(['signature' => 'Invalid or expired webhook signature.']);
        }
    }
}
