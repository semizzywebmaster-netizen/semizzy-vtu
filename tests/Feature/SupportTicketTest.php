<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_ticket_with_first_message(): void
    {
        $user = $this->makeUser('ticket-owner@example.test');

        $response = $this->actingAs($user)->post('/support', [
            'subject' => 'Cannot access service',
            'category' => 'service',
            'message' => 'The service page is unavailable.',
        ]);

        $ticket = SupportTicket::query()->firstOrFail();
        $response->assertRedirect('/support/'.$ticket->id);
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame('open', $ticket->status);
        $this->assertSame('The service page is unavailable.', $ticket->messages()->firstOrFail()->message);
    }

    public function test_user_can_only_see_their_own_tickets(): void
    {
        $owner = $this->makeUser('owner-ticket@example.test');
        $other = $this->makeUser('other-ticket@example.test');
        $ticket = SupportTicket::create([
            'user_id' => $owner->id, 'reference' => 'SUP-OWNERTICKET', 'subject' => 'Private ticket',
            'category' => 'general', 'priority' => 'normal', 'status' => 'open',
        ]);

        $this->actingAs($other)->get('/support/'.$ticket->id)->assertNotFound();
        $this->actingAs($other)->get('/support')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Support')->has('tickets.data', 0));
    }

    public function test_staff_can_view_and_reply_to_ticket(): void
    {
        $owner = $this->makeUser('ticket-customer@example.test');
        $staff = $this->makeUser('ticket-staff@example.test', 'SUPPORT');
        $ticket = SupportTicket::create([
            'user_id' => $owner->id, 'reference' => 'SUP-STAFFTICKET', 'subject' => 'Need assistance',
            'category' => 'general', 'priority' => 'normal', 'status' => 'open',
        ]);
        $ticket->messages()->create(['user_id' => $owner->id, 'message' => 'Please help me.']);

        $this->actingAs($staff)->post('/support/'.$ticket->id.'/reply', ['message' => 'We are checking this.'])->assertRedirect();
        $this->assertSame(2, $ticket->messages()->count());
        $this->assertSame('pending', $ticket->fresh()->status);
    }

    public function test_staff_status_changes_are_audited(): void
    {
        $owner = $this->makeUser('ticket-status-owner@example.test');
        $staff = $this->makeUser('ticket-status-staff@example.test', 'SUPPORT');
        $ticket = SupportTicket::create([
            'user_id' => $owner->id,
            'reference' => 'SUP-STATUSCHANGE',
            'subject' => 'Status audit test',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($staff)->patch('/support/'.$ticket->id.'/status', ['status' => 'resolved'])->assertRedirect();

        $this->assertSame('resolved', $ticket->fresh()->status);
        $event = AuditEvent::query()->where('event', 'support.ticket.status_changed')->firstOrFail();
        $this->assertSame(['from' => 'open', 'to' => 'resolved'], $event->context);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/support')->assertRedirect('/login');
    }

    private function makeUser(string $email, string $role = 'USER'): User
    {
        return User::create([
            'name' => 'Support Test User', 'email' => $email,
            'password' => 'Strong-Password-123!', 'role' => $role, 'status' => 'active',
        ]);
    }
}
