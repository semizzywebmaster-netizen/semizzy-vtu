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

    public function test_only_one_concurrent_webhook_handler_can_begin_processing(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);
        $receipt = $guard->claim($provider, 'evt_processing', '{"ok":true}');

        $this->assertTrue($guard->beginProcessing($receipt));
        $this->assertFalse($guard->beginProcessing($receipt->fresh()));
        $this->assertDatabaseHas('webhook_receipts', [
            'id' => $receipt->id,
            'status' => 'processing',
        ]);
    }

    public function test_failed_webhook_processing_can_be_retried_safely(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $receipt = $guard->claim($provider, 'evt_retry', '{"ok":true}');
        $guard->beginProcessing($receipt);
        $guard->markFailed($receipt->fresh(), 'Temporary processing failure.');

        $this->assertTrue($guard->beginProcessing($receipt->fresh()));
        $this->assertDatabaseHas('webhook_receipts', [
            'id' => $receipt->id,
            'status' => 'processing',
            'processing_error' => null,
        ]);
    }

    public function test_stale_processing_claim_can_be_recovered_but_active_claim_cannot(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $receipt = $guard->claim($provider, 'evt_stale', '{"ok":true}');
        $this->assertTrue($guard->beginProcessing($receipt));
        $receipt->refresh();

        $this->assertFalse($guard->beginProcessing($receipt));

        $receipt->forceFill(['processing_started_at' => now()->subMinutes(11)])->save();
        $this->assertTrue($guard->beginProcessing($receipt->fresh()));
        $this->assertNotSame($receipt->processing_token, $receipt->fresh()->processing_token);
    }

    public function test_old_processing_token_cannot_finalize_a_reclaimed_webhook(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $receipt = $guard->claim($provider, 'evt_token', '{"ok":true}');
        $this->assertTrue($guard->beginProcessing($receipt));
        $first = $receipt->fresh();
        $firstToken = $guard->processingToken($first);

        $first->forceFill(['processing_started_at' => now()->subMinutes(11)])->save();
        $this->assertTrue($guard->beginProcessing($first->fresh()));
        $second = $first->fresh();
        $secondToken = $guard->processingToken($second);

        $guard->markProcessed($second, $firstToken);
        $this->assertSame('processing', $second->fresh()->status);

        $guard->markProcessed($second->fresh(), $secondToken);
        $this->assertSame('processed', $second->fresh()->status);
    }

    public function test_webhook_event_can_be_marked_processed_or_failed(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $receipt = $guard->claim($provider, 'evt_status', '{"ok":true}');
        $this->assertTrue($guard->beginProcessing($receipt));
        $processing = $receipt->fresh();
        $guard->markProcessed($processing, $guard->processingToken($processing));

        $this->assertDatabaseHas('webhook_receipts', [
            'id' => $receipt->id,
            'status' => 'processed',
        ]);

        $failed = $guard->claim($provider, 'evt_failed', '{"ok":false}');
        $this->assertTrue($guard->beginProcessing($failed));
        $failedProcessing = $failed->fresh();
        $guard->markFailed($failedProcessing, 'Authorization: Bearer provider-secret-123; api_key=provider-secret-123', $guard->processingToken($failedProcessing));

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

    public function test_same_event_id_with_different_signature_is_rejected(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);

        $guard->claim($provider, 'evt_signature_mismatch', '{"amount":100}', '1700000000.signature-a');

        $this->expectException(\\RuntimeException::class);
        $this->expectExceptionMessage('Webhook event ID was already claimed with a different signature.');

        $guard->claim($provider, 'evt_signature_mismatch', '{"amount":100}', '1700000000.signature-b');
    }

    public function test_late_failure_cannot_regress_a_processed_webhook_event(): void
    {
        $provider = $this->provider();
        $guard = app(WebhookReplayGuard::class);
        $receipt = $guard->claim($provider, 'evt_out_of_order', '{"status":"success"}');

        $this->assertTrue($guard->beginProcessing($receipt));
        $processing = $receipt->fresh();
        $token = $guard->processingToken($processing);
        $guard->markProcessed($processing, $token);

        $guard->markFailed($receipt->fresh(), 'Late stale failure callback.', $token);

        $this->assertDatabaseHas('webhook_receipts', [
            'id' => $receipt->id,
            'status' => 'processed',
        ]);
        $this->assertFalse($guard->beginProcessing($receipt->fresh()));
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
