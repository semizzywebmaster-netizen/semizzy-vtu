<?php

namespace App\\Services\\Audit;

use App\\Models\\AuditEvent;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Str;

class AuditLogger
{
    public function record(string $event, ?object $subject = null, array $context = [], ?Request $request = null): AuditEvent
    {
        $request ??= request();

        return AuditEvent::create([
            'actor_id' => optional($request->user())->id,
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'request_id' => $request?->header('X-Request-ID') ?: (string) Str::uuid(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $context,
        ]);
    }
}
