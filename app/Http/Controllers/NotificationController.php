<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Notifications', [
            'notifications' => $user->notifications()
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'type' => class_basename($notification->type),
                    'title' => (string) ($notification->data['title'] ?? 'Notification'),
                    'message' => (string) ($notification->data['message'] ?? ''),
                    'url' => $this->safeInternalUrl($notification->data['url'] ?? null),
                    'readAt' => $notification->read_at?->toISOString(),
                    'createdAt' => $notification->created_at?->toISOString(),
                ])
                ->values(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->first();

        abort_unless($record !== null, 404);

        try {
            $record->markAsRead();

            return back()->with('success', 'Notification marked as read.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Notification could not be marked as read safely.');
        }
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        try {
            $request->user()->unreadNotifications()->update(['read_at' => now()]);
            return back()->with('success', 'All notifications marked as read.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Notifications could not be marked as read safely.');
        }
    }

    private function safeInternalUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return $url;
    }
}
