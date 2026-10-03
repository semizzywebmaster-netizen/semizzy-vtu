<?php

namespace App\Services\Providers;

use InvalidArgumentException;

final class ProviderUrlGuard
{
    public function validate(?string $url): void
    {
        if (!$url) {
            return;
        }

        $parts = parse_url($url);

        if (
            !$parts
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'http'], true)
            || empty($parts['host'])
            || array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)
        ) {
            throw new InvalidArgumentException('Provider URL must use HTTP or HTTPS, contain a valid host, and not embed credentials.');
        }

        $host = strtolower((string) $parts['host']);

        if (in_array($host, ['localhost', 'localhost.localdomain', 'metadata.google.internal'], true)) {
            throw new InvalidArgumentException('Local and metadata provider hosts are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if ($this->isPrivateOrReserved($host)) {
                throw new InvalidArgumentException('Private, loopback, or reserved provider IP addresses are not allowed.');
            }

            return;
        }

        // Prevent DNS-based SSRF. A public hostname can resolve to a private,
        // loopback, link-local, multicast, or otherwise reserved address.
        foreach ($this->resolveHost($host) as $ip) {
            if ($this->isPrivateOrReserved($ip)) {
                throw new InvalidArgumentException('Provider hostname resolves to a private or reserved address.');
            }
        }
    }

    private function resolveHost(string $host): array
    {
        $ips = [];

        foreach ((array) @gethostbynamel($host) as $ip) {
            $ips[] = $ip;
        }

        if (function_exists('dns_get_record')) {
            foreach ((array) @dns_get_record($host, DNS_AAAA) as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPrivateOrReserved(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
