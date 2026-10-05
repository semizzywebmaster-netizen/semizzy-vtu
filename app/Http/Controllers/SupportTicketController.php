<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\CoreNotification;
use App\Services\Audit\AuditLogger;
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

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', 'in:general,account,service,provider,security,other'],
            'message' => ['required', 'string', 'min:5', 'max:10000'],
        ]);

        try {
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

            try { $audit->record('support.ticket.created', $ticket, ['category' => $ticket->category], $request); } catch (\Throwable $e) { report($e); }
            try { $request->user()->notify(new CoreNotification('Support ticket created','Your support ticket '.$ticket->reference.' was created.','/support/'.$ticket->id)); } catch (\Throwable $e) { report($e); }
            try {
                User::query()->where('status', 'active')->whereIn('role', ['ADMIN', 'STAFF', 'SUPPORT'])
                    ->whereKeyNot($request->user()->id)->get()
                    ->each(fn (User $recipient) => $recipient->notify(new CoreNotification('New support ticket','A new support ticket '.$ticket->reference.' needs attention.','/support/'.$ticket->id)));
            } catch (\Throwable $e) { report($e); }

            return redirect()->route('support.show', $ticket)->with('success', 'Support ticket created.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Support ticket could not be created safely.');
        }
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

    public function reply(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        try {
            $this->authorizeTicket($request, $ticket);
            $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:10000']]);
            [$ticket, $isStaffReply] = DB::transaction(function () use ($request, $ticket, $data): array {
                $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
                abort_if(in_array($lockedTicket->status, ['closed', 'resolved'], true) && ! $request->user()->hasRole(['ADMIN', 'STAFF', 'SUPPORT']), 409, 'This ticket is closed.');
                $isStaffReply = $request->user()->hasRole(['ADMIN', 'STAFF', 'SUPPORT']);
                $lockedTicket->messages()->create(['user_id' => $request->user()->id, 'message' => $data['message']]);
                $lockedTicket->forceFill(['last_reply_at' => now(), 'status' => $isStaffReply ? 'pending' : 'open'])->saveOrFail();
                return [$lockedTicket->fresh(), $isStaffReply];
            });
            try { $audit->record('support.ticket.replied', $ticket, ['staff_reply' => $isStaffReply], $request); } catch (\Throwable $e) { report($e); }
            try {
                if ($isStaffReply) $ticket->user()->first()?->notify(new CoreNotification('Support replied','Support replied to ticket '.$ticket->reference.'.','/support/'.$ticket->id));
                else User::query()->where('status','active')->whereIn('role',['ADMIN','STAFF','SUPPORT'])->get()->each(fn(User $recipient)=>$recipient->notify(new CoreNotification('Customer replied','A customer replied to ticket '.$ticket->reference.'.','/support/'.$ticket->id)));
            } catch (\Throwable $e) { report($e); }
            return back()->with('success', 'Reply added.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Support reply failed safely.');
        }
    }

    public function updateStatus(Request $request, SupportTicket $ticket, AuditLogger $audit): RedirectResponse
    {
        try {
            abort_unless($request->user()->hasRole(['ADMIN', 'STAFF', 'SUPPORT']), 403);
            $data = $request->validate(['status' => ['required', 'in:open,pending,resolved,closed']]);
            DB::transaction(function () use ($ticket, $data, $request, $audit): void {
                $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
                $previousStatus = $ticket->status;
                if ($previousStatus === $data['status']) return;
                $ticket->updateOrFail(['status' => $data['status']]);
                try { $audit->record('support.ticket.status_changed', $ticket, ['from'=>$previousStatus,'to'=>$data['status']], $request); } catch (\Throwable $e) { report($e); }
            });
            return back()->with('success', 'Ticket status updated.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Ticket status update failed safely.');
        }
    }

    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        abort_unless(
            $ticket->user_id === $request->user()->id || $request->user()->hasRole(['ADMIN', 'STAFF', 'SUPPORT']),
            404
        );
    }
}
