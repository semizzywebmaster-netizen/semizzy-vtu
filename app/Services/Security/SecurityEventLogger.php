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
            'user_id' => $request?->user()?->id,
            'event' => $event,
            'severity' => $severity,
            'request_id' => $request?->header('X-Request-ID') ?: (string) Str::uuid(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $this->sanitizeContext($context),
        ]);
    }

    private function sanitizeContext(array $context): array
    {
        $sensitive = ['token', 'access_token', 'api_key', 'secret', 'password', 'authorization', 'credentials'];

        foreach ($sensitive as $key) {
            unset($context[$key]);
        }

        return $context;
    }
}
