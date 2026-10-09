<?php

namespace Tests\Unit;

use App\Services\Security\WebhookSignatureService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebhookSignatureServiceTest extends TestCase
{
    public function test_valid_timestamped_hmac_signature_is_accepted(): void
    {
        $service = app(WebhookSignatureService::class);
        $payload = '{"event":"payment.success","amount":2500}';
        $signature = $service->sign($payload, 'test-webhook-secret', 1700000000);

        $this->assertTrue($service->verify($payload, $signature, 'test-webhook-secret', 300, 1700000000));
    }

    public function test_payload_tampering_is_rejected(): void
    {
        $service = app(WebhookSignatureService::class);
        $signature = $service->sign('{"amount":2500}', 'test-webhook-secret', 1700000000);

        $this->assertFalse($service->verify('{"amount":2501}', $signature, 'test-webhook-secret', 300, 1700000000));
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $service = app(WebhookSignatureService::class);
        $signature = $service->sign('{"event":"paid"}', 'right-secret', 1700000000);

        $this->assertFalse($service->verify('{"event":"paid"}', $signature, 'wrong-secret', 300, 1700000000));
    }

    public function test_expired_signature_is_rejected(): void
    {
        $service = app(WebhookSignatureService::class);
        $signature = $service->sign('{"event":"paid"}', 'test-webhook-secret', 1700000000);

        $this->assertFalse($service->verify('{"event":"paid"}', $signature, 'test-webhook-secret', 300, 1700000401));
    }

    public function test_malformed_and_missing_signatures_are_rejected(): void
    {
        $service = app(WebhookSignatureService::class);

        $this->assertFalse($service->verify('{}', 'not-a-signature', 'test-webhook-secret', 300, 1700000000));
        $this->assertFalse($service->verify('{}', '', 'test-webhook-secret', 300, 1700000000));
        $this->assertFalse($service->verify('{}', '1700000000.digest', '', 300, 1700000000));
    }

    public function test_assert_valid_throws_for_invalid_signature(): void
    {
        $this->expectException(ValidationException::class);

        app(WebhookSignatureService::class)->assertValid('{}', 'invalid', 'test-webhook-secret', 300, 1700000000);
    }
}
