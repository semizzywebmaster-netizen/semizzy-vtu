<?php

namespace Tests\Feature\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Semizzy\Addons\Payments\Models\PaymentWebhookEvent;
use Semizzy\Addons\Payments\Services\PaymentGatewayManager;
use Semizzy\Addons\Payments\Services\PaymentWebhookService;
use Tests\TestCase;

class PaymentWebhookReplayRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reused_provider_event_id_with_changed_payload_is_rejected(): void
    {
        $provider = $this->provider();
        $manager = \\Mockery::mock(PaymentGatewayManager::class);
        $adapter = \\Mockery::mock(PaymentGatewayAdapter::class);
        $manager->shouldReceive('adapter')->once()->with($provider)->andReturn($adapter);
        $adapter->shouldReceive('verifyCollection')->once()->with($provider, 'pay-ref-1')->andReturn([
            'status' => 'success',
            'amount' => 1000,
            'currency' => 'NGN',
        ]);
        $this->app->instance(PaymentGatewayManager::class, $manager);
        $service = app(PaymentWebhookService::class);

        $firstPayload = [
            'event' => 'charge.success',
            'data' => ['id' => 7001, 'reference' => 'pay-ref-1', 'status' => 'success', 'amount' => 1000],
        ];
        $this->setRawRequest($firstPayload);

        try {
            $service->handle($provider, $firstPayload, [
                'x-paystack-signature' => $this->signature($firstPayload),
            ]);
            $this->fail('The missing payment intent should stop this callback after its event is recorded.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Payment reference was not found.', $exception->getMessage());
        }

        $changedPayload = $firstPayload;
        $changedPayload['data']['amount'] = 2000;
        $this->setRawRequest($changedPayload);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Webhook event ID was already received with a different payload.');

        $service->handle($provider, $changedPayload, [
            'x-paystack-signature' => $this->signature($changedPayload),
        ]);
    }

    public function test_processed_payment_webhook_duplicate_returns_existing_event_without_reprocessing(): void
    {
        $provider = $this->provider();
        $payload = [
            'event' => 'charge.success',
            'data' => ['id' => 7002, 'reference' => 'pay-ref-2', 'status' => 'success', 'amount' => 1000],
        ];
        $event = PaymentWebhookEvent::create([
            'provider_key' => $provider->code,
            'event_id' => '7002',
            'event_type' => 'charge.success',
            'signature_hash' => hash('sha256', $this->signature($payload)),
            'processing_status' => 'processed',
            'payment_reference' => 'pay-ref-2',
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        $manager = \\Mockery::mock(PaymentGatewayManager::class);
        $manager->shouldNotReceive('adapter');
        $this->app->instance(PaymentGatewayManager::class, $manager);
        $this->setRawRequest($payload);

        $result = app(PaymentWebhookService::class)->handle($provider, $payload, [
            'x-paystack-signature' => $this->signature($payload),
        ]);

        $this->assertSame($event->id, $result->id);
        $this->assertSame('processed', $result->processing_status);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    private function provider(): PaymentGatewayProvider
    {
        return PaymentGatewayProvider::create([
            'name' => 'Paystack Regression Provider',
            'code' => 'paystack-regression',
            'driver' => 'paystack',
            'credentials' => ['webhook_secret' => 'test-paystack-secret'],
            'settings' => ['require_webhook_signature' => true],
            'enabled' => true,
            'paused' => false,
            'maintenance' => false,
            'priority' => 1,
            'weight' => 100,
        ]);
    }

    private function setRawRequest(array $payload): void
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw);
        $this->app->instance('request', $request);
    }

    private function signature(array $payload): string
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash_hmac('sha512', $raw, 'test-paystack-secret');
    }
}
