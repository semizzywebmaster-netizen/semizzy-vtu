<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Services\Security\WebhookReplayGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookReplayGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_webhook_event_is_claimed_without_storing_raw_payload(): void
    {
        $provider = $this->provider();

        $receipt = app(WebhookReplayGuard::class)->claim(
            $provider,
            'evt_001',
            '{"amount":100,"secret":"must-not-be-stored"}',
            '1700000000.signature'
        );

        $this->assertSame('received', $receipt->status);
        $this->assertNotSame('{"amount":100,"secret":"must-not-be-stored"}', $receipt->payload_hash);
        $this->assertSame(64, strlen($receipt->payload_hash));
        $this->assertSame(64, strlen($receipt->signature_hash));

        $this->assertDatabaseMissing('webhook_receipts', [
            'event_id' => 'evt_001',
            'payload_hash' => '{"amount":100,"secret":"must-not-be-stored"}',
        ]);
    }

    public function test_duplicate_webhook_event_returns_original_receipt(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $first = $guard->claim($provider, 'evt_duplicate', '{"ok":true}');
        $second = $guard->claim($provider, 'evt_duplicate', '{"ok":true}');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('webhook_receipts', 1);
    }


    public function test_same_event_id_with_different_payload_is_rejected(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $guard->claim($provider, 'evt_integrity', '{"amount":100}');

        $this->expectException(\RuntimeException::class);
        $guard->claim($provider, 'evt_integrity', '{"amount":999}');
    }

    public function test_webhook_event_can_be_marked_processed_or_failed(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $receipt = $guard->claim($provider, 'evt_status', '{"ok":true}');
        $guard->markProcessed($receipt->fresh());

        $this->assertDatabaseHas('webhook_receipts', [
            'id' => $receipt->id,
            'status' => 'processed',
        ]);

        $failed = $guard->claim($provider, 'evt_failed', '{"ok":false}');
        $guard->markFailed($failed, 'Authorization: Bearer provider-secret-123; api_key=provider-secret-123');

        $this->assertDatabaseHas('webhook_receipts', [
            'id' => $failed->id,
            'status' => 'failed',
        ]);

        $stored = $failed->fresh()->processing_error;
        $this->assertStringNotContainsString('provider-secret-123', $stored);
        $this->assertStringContainsString('[REDACTED]', $stored);
    }

    public function test_event_id_is_required_and_bounded(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $this->expectException(\RuntimeException::class);
        $guard->claim($provider, '   ', '{}');
    }

    private function provider(): ApiProvider
    {
        return ApiProvider::create([
            'identifier' => 'webhook-test-provider',
            'display_name' => 'Webhook Test Provider',
            'verification_status' => 'sandbox_verified',
            'integration_status' => 'sandbox_verified',
            'enabled' => false,
            'paused' => false,
            'priority' => 1,
        ]);
    }
}
