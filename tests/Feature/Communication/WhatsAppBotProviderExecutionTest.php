<?php

namespace Tests\Feature\Communication;

use Addons\CommunicationWhatsapp\Services\WhatsAppWebhookService;
use Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway;
use App\Models\Communication\Conversation;
use App\Models\Communication\DeliveryAttempt;
use App\Models\Communication\Message;
use App\Models\Communication\Provider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

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
    public function test_ambiguous_send_timeout_does_not_fail_over_to_another_whatsapp_provider(): void
    {
        foreach (['Primary', 'Secondary'] as $index => $name) {
            Provider::query()->create([
                'channel' => 'whatsapp',
                'name' => 'WhatsApp ' . $name,
                'driver' => 'generic_http',
                'credentials' => ['url' => 'https://provider-' . strtolower($name) . '.example/messages'],
                'enabled' => true,
                'paused' => false,
                'priority' => $index + 1,
            ]);
        }

        $conversation = Conversation::query()->create([
            'channel' => 'whatsapp',
            'external_contact' => '+2348012345678',
            'status' => 'open',
        ]);
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'channel' => 'whatsapp',
            'direction' => 'outbound',
            'recipient' => '+2348012345678',
            'body' => 'Test delivery',
            'status' => 'queued',
            'idempotency_key' => 'wa-test-ambiguous-send',
        ]);

        $calls = 0;
        Http::fake(function ($request, $options) use (&$calls) {
            $calls++;
            throw new ConnectionException('Connection timed out after dispatch.');
        });

        try {
            app(CommunicationProviderGateway::class)->send($message);
            $this->fail('An ambiguous send must stop failover and require status reconciliation.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Communication provider outcome is unknown; verify delivery status before retrying.',
                $exception->getMessage()
            );
        }

        $this->assertSame(1, $calls, 'The secondary provider must not receive a possibly duplicate send.');
        $this->assertSame('pending', $message->fresh()->status);
        $this->assertSame(1, DeliveryAttempt::query()->where('message_id', $message->id)->count());
        $this->assertSame('unknown', DeliveryAttempt::query()->where('message_id', $message->id)->value('status'));
    }

    public function test_webhook_redacts_transaction_pin_from_persisted_message_and_metadata(): void
    {
        $provider = Provider::query()->create([
            'channel' => 'whatsapp',
            'name' => 'WhatsApp Redaction Test Provider',
            'driver' => 'generic_http',
            'credentials' => ['webhook_secret' => 'redaction-secret'],
            'enabled' => true,
            'paused' => false,
            'priority' => 1,
        ]);
        $body = json_encode([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id' => 'wamid.pin-redaction-001',
                            'from' => '2348011112222',
                            'type' => 'text',
                            'text' => ['body' => 'BUY 12 08098765432 500 PIN 9876'],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'redaction-secret');

        app(WhatsAppWebhookService::class)->handle($provider, $body, $signature);

        $message = Message::query()->where('idempotency_key', 'whatsapp:wamid.pin-redaction-001')->firstOrFail();
        $this->assertStringNotContainsString('PIN 9876', (string) $message->body);
        $this->assertStringNotContainsString('9876', json_encode($message->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('[REDACTED]', (string) $message->body);
    }

}
