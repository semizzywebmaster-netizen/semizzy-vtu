<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditEventController extends Controller
{
    private const SENSITIVE_KEYS = ['token', 'api_key', 'secret', 'password', 'authorization', 'credentials', 'private_key', 'refresh_token', 'otp'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:120'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'request_id' => ['nullable', 'string', 'max:128'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $events = AuditEvent::query()
            ->with('actor:id,name,email')
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', 'like', '%'.$event.'%'))
            ->when($filters['actor_id'] ?? null, fn ($query, int $actorId) => $query->where('actor_id', $actorId))
            ->when($filters['request_id'] ?? null, fn ($query, string $requestId) => $query->where('request_id', $requestId))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', $to))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditEvent $event): array => [
                'id' => $event->id,
                'event' => $event->event,
                'actor' => $event->actor?->name ?? 'System / former user',
                'actorId' => $event->actor_id,
                'subjectType' => $event->auditable_type ? class_basename($event->auditable_type) : null,
                'subjectId' => $event->auditable_id,
                'requestId' => $event->request_id,
                'ipAddress' => $event->ip_address,
                'context' => $this->sanitize(is_array($event->context) ? $event->context : []),
                'createdAt' => $event->created_at?->toISOString(),
            ]);

        return Inertia::render('Admin/AuditEvents', ['events' => $events, 'filters' => $filters]);
    }

    private function sanitize(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);
            $sensitive = false;

            foreach (self::SENSITIVE_KEYS as $needle) {
                if (str_contains($normalized, $needle)) {
                    $sensitive = true;
                    break;
                }
            }

            $safe[$key] = $sensitive ? '[REDACTED]' : (is_array($value) ? $this->sanitize($value) : $value);
        }

        return $safe;
    }
}
