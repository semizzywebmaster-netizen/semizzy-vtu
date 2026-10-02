<?php

namespace App\\Services\\Providers;

use InvalidArgumentException;

final class ProviderUrlGuard
{
    public function validate(?string $url): void
    {
        if (!$url) return;
        $parts=parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme']??''),['https','http'],true) || empty($parts['host'])) {
            throw new InvalidArgumentException('Provider URL must use HTTP or HTTPS and contain a valid host.');
        }
        $host=strtolower($parts['host']);
        if (in_array($host,['localhost','localhost.localdomain','metadata.google.internal'],true)) {
            throw new InvalidArgumentException('Local and metadata provider hosts are not allowed.');
        }
        if (filter_var($host,FILTER_VALIDATE_IP) !== false && $this->isPrivate($host)) {
            throw new InvalidArgumentException('Private or loopback provider IP addresses are not allowed.');
        }
    }

    private function isPrivate(string $ip): bool
    {
        return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
