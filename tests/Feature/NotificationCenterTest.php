<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_only_their_notifications(): void
    {
        $user = $this->makeUser('notify@example.test');
        $other = $this->makeUser('other-notify@example.test');
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CoreNotification',
            'data' => ['title' => 'Account update', 'message' => 'Your profile was updated.'],
        ]);
        $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CoreNotification',
            'data' => ['title' => 'Private update', 'message' => 'Not yours.'],
        ]);

        $this->actingAs($user)->get('/notifications')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notifications')
                ->where('unreadCount', 1)
                ->has('notifications', 1)
                ->where('notifications.0.title', 'Account update')
                ->missing('notifications.0.data')
            );
    }

    public function test_user_can_mark_one_notification_as_read(): void
    {
        $user = $this->makeUser('read-one@example.test');
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CoreNotification',
            'data' => ['title' => 'Read me'],
        ]);

        $this->actingAs($user)->post('/notifications/'.$notification->id.'/read')->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = $this->makeUser('reader@example.test');
        $other = $this->makeUser('owner@example.test');
        $notification = $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CoreNotification',
            'data' => ['title' => 'Private'],
        ]);

        $this->actingAs($user)->post('/notifications/'.$notification->id.'/read')->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_read(): void
    {
        $user = $this->makeUser('read-all@example.test');
        foreach (range(1, 2) as $index) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\CoreNotification',
                'data' => ['title' => 'Notice '.$index],
            ]);
        }

        $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'name' => 'Notification User',
            'email' => $email,
            'password' => 'Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);
    }
}
