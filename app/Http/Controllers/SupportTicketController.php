<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tickets = SupportTicket::query()
            ->when(! $user->hasRole(['ADMIN', 'STAFF', 'SUPPORT']), fn ($query) => $query->where('user_id', $user->id))
            ->with('user:id,name,email')
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate(20)
            ->through(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'messageCount' => $ticket->messages_count,
                'updatedAt' => $ticket->updated_at?->toISOString(),
                'requester' => $user->hasRole(['ADMIN', 'STAFF', 'SUPPORT']) ? $ticket->user?->name : null,
            ]);

        return Inertia::render('Support', ['tickets' => $tickets]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', 'in:general,account,service,provider,security,other'],
            'message' => ['required', 'string', 'min:5', 'max:10000'],
        ]);

        $ticket = DB::transaction(function () use ($request, $data): SupportTicket {
            $ticket = SupportTicket::create([
                'user_id' => $request->user()->id,
                'reference' => 'SUP-'.Str::upper(Str::random(10)),
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => 'normal',
                'status' => 'open',
                'last_reply_at' => now(),
            ]);
            $ticket->messages()->create(['user_id' => $request->user()->id, 'message' => $data['message']]);

            return $ticket;
        });

        return redirect()->route('support.show', $ticket)->with('success', 'Support ticket created.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $this->authorizeTicket($request, $ticket);
        $user = $request->user();

        return Inertia::render('SupportTicket', [
            'ticket' => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'requester' => $ticket->user()->first(['id', 'name', 'email'])?->name,
                'messages' => $ticket->messages()->with('user:id,name,role')->oldest()->get()->map(fn ($message): array => [
                    'id' => $message->id,
                    'author' => $message->user?->name ?? 'Former user',
                    'staff' => $message->user?->hasRole(['ADMIN', 'STAFF', 'SUPPORT']) ?? false,
                    'message' => $message->message,
                    'createdAt' => $message->created_at?->toISOString(),
                ])->values(),
            ],
            'canManage' => $user->hasRole(['ADMIN', 'STAFF', 'SUPPORT']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($request, $ticket);
        abort_if(in_array($ticket->status, ['closed', 'resolved'], true) && ! $request->user()->hasRole(['ADMIN', 'STAFF', 'SUPPORT']), 409, 'This ticket is closed.');

        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:10000']]);
        DB::transaction(function () use ($request, $ticket, $data): void {
            $ticket->messages()->create(['user_id' => $request->user()->id, 'message' => $data['message']]);
            $ticket->forceFill([
                'last_reply_at' => now(),
                'status' => $request->user()->hasRole(['ADMIN', 'STAFF', 'SUPPORT']) ? 'pending' : 'open',
            ])->save();
        });

        return back()->with('success', 'Reply added.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($request->user()->hasRole(['ADMIN', 'STAFF', 'SUPPORT']), 403);
        $data = $request->validate(['status' => ['required', 'in:open,pending,resolved,closed']]);
        $ticket->update(['status' => $data['status']]);

        return back()->with('success', 'Ticket status updated.');
    }

    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        abort_unless(
            $ticket->user_id === $request->user()->id || $request->user()->hasRole(['ADMIN', 'STAFF', 'SUPPORT']),
            404
        );
    }
}
