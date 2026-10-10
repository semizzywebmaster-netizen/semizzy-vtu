<?php

namespace Tests\\Feature\\Communication;

use Addons\\CommunicationWhatsapp\\Services\\WhatsAppWebhookService;
use App\\Models\\Communication\\Message;
use App\\Models\\Communication\\Provider;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class WhatsAppBotProviderExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_signature_selects_the_provider_that_signed_the_payload(): void
    {
        $first = Provider::query()->create([
            'channel' => 'whatsapp',
            'name' => 'WhatsApp Provider A',
            'driver' => 'generic_http',
            'credentials' => ['webhook_secret' => 'provider-a-secret'],
            'enabled' => true,
            'paused' => false,
            'priority' => 1,
        ]);
        $second = Provider::query()->create([
            'channel' => 'whatsapp',
            'name' => 'WhatsApp Provider B',
            'driver' => 'generic_http',
            'credentials' => ['webhook_secret' => 'provider-b-secret'],
            'enabled' => true,
            'paused' => false,
            'priority' => 2,
        ]);

        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'provider-b-secret');
        $service = app(WhatsAppWebhookService::class);

        $this->assertFalse($service->matchesSignature($first, $body, $signature));
        $this->assertTrue($service->matchesSignature($second, $body, $signature));
        $this->assertFalse($service->matchesSignature($second, $body, 'sha256=invalid'));
        $this->assertFalse($service->matchesSignature($second, $body, null));
    }

    public function test_duplicate_provider_webhook_does_not_store_the_same_inbound_message_twice(): void
    {
        $provider = Provider::query()->create([
            'channel' => 'whatsapp',
            'name' => 'WhatsApp Test Provider',
            'driver' => 'generic_http',
            'credentials' => ['webhook_secret' => 'test-webhook-secret'],
            'enabled' => true,
            'paused' => false,
            'priority' => 1,
        ]);

        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => 'phone-number-1'],
                        'messages' => [[
                            'id' => 'wamid.test-message-001',
                            'from' => '2348012345678',
                            'type' => 'text',
                            'text' => ['body' => 'MENU'],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'test-webhook-secret');
        $service = app(WhatsAppWebhookService::class);

        $this->assertSame(1, $service->handle($provider, $body, $signature));
        $this->assertSame(0, $service->handle($provider, $body, $signature));
        $this->assertSame(1, Message::query()->where('channel', 'whatsapp')->where('direction', 'inbound')->count());
        $this->assertSame('whatsapp:wamid.test-message-001', Message::query()->where('direction', 'inbound')->value('idempotency_key'));
    }
}
