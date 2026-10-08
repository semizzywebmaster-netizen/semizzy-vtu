<?php

namespace Semizzy\Addons\ForexDigitalAssets\Services;

use InvalidArgumentException;

class ForexQuoteNormalizer
{
    public function normalize(array $payload): array
    {
        $bid = $this->number($payload['bid'] ?? null, 'bid');
        $ask = $this->number($payload['ask'] ?? null, 'ask');
        $mid = $this->number($payload['mid'] ?? null, 'mid');

        if ($mid === null && $bid !== null && $ask !== null) {
            $mid = bcdiv(bcadd($bid, $ask, 12), '2', 12);
        }

        if ($mid === null) {
            throw new InvalidArgumentException('A real provider quote must contain mid, or both bid and ask.');
        }

        if ($bid !== null && $ask !== null && bccomp($bid, $ask, 12) > 0) {
            throw new InvalidArgumentException('Bid cannot exceed ask.');
        }

        return [
            'bid' => $bid,
            'ask' => $ask,
            'mid' => $mid,
            'open' => $this->number($payload['open'] ?? null, 'open'),
            'high' => $this->number($payload['high'] ?? null, 'high'),
            'low' => $this->number($payload['low'] ?? null, 'low'),
            'close' => $this->number($payload['close'] ?? null, 'close'),
            'volume' => $this->number($payload['volume'] ?? null, 'volume'),
            'source_reference' => $this->requiredString($payload['source_reference'] ?? null),
            'observed_at' => $payload['observed_at'] ?? now(),
            'expires_at' => $payload['expires_at'] ?? null,
        ];
    }

    private function number(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException("Invalid {$field} value.");
        }
        return (string) $value;
    }

    private function requiredString(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Provider source reference is required.');
        }
        return trim($value);
    }
}
