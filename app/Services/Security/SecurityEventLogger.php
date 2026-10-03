<?php

namespace App\Services\Security;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityEventLogger
{
    public function record(string $event, string $severity = 'info', array $context = [], ?Request $request = null): SecurityEvent
    {
        $request ??= request();

        return SecurityEvent::create([
            'user_id' => $request?->user()?->id ?? auth()->id(),
            'event' => $event,
            'severity' => $severity,
            'request_id' => $request?->attributes->get('request_id')
                ?: $request?->header('X-Request-ID')
                ?: (string) Str::uuid(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $this->sanitizeContext($context),
        ]);
    }

    private function sanitizeContext(array $context): array
    {
        $sensitive = [
            'token',
            'access_token',
            'api_key',
            'secret',
            'password',
            'authorization',
            'credentials',
            'client_secret',
            'private_key',
            'refresh_token',
        ];

        $sanitized = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower(str_replace(['-', ' '], '_', (string) $key));

            if (in_array($normalizedKey, $sensitive, true)
                || preg_match('/(?:^|_)(?:token|secret|password|authorization|credential|private_key)(?:_|$)/', $normalizedKey)
                || str_contains($normalizedKey, 'api_key')
            ) {
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeContext($value)
                : $value;
        }

        return $sanitized;
    }
}
