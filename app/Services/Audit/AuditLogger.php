<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'token', 'access_token', 'api_key', 'secret', 'password', 'authorization',
        'credentials', 'client_secret', 'private_key', 'refresh_token', 'otp',
        'one_time_code', 'webhook_secret',
    ];

    public function record(string $event, ?object $subject = null, array $context = [], ?Request $request = null): AuditEvent
    {
        $request ??= request();

        return AuditEvent::create([
            'actor_id' => $request->user()?->id,
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'request_id' => $request->attributes->get('request_id')
                ?: $request->header('X-Request-ID')
                ?: (string) Str::uuid(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'context' => $this->sanitize($context),
        ]);
    }

    private function sanitize(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $sensitive = false;

            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($normalizedKey, $sensitiveKey)) {
                    $sensitive = true;
                    break;
                }
            }

            if ($sensitive) {
                $safe[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
            } else {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
