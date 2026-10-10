<?php

namespace Addons\\WhatsAppBot\\Http\\Controllers;

use Addons\\CommunicationWhatsapp\\Services\\WhatsAppWebhookService;
use Addons\\CommunicationWhatsapp\\Services\\CommunicationProviderGateway;
use App\\Models\\Addon;
use App\\Models\\Communication\\Conversation;
use App\\Models\\Communication\\Message;
use App\\Models\\Communication\\Provider;
use App\\Models\\ServiceProduct;
use App\\Models\\User;
use App\\Services\\Vtu\\VtuPayloadValidator;
use App\\Services\\Vtu\\VtuTransactionService;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Response;
use Illuminate\\Support\\Facades\\Hash;

class WhatsAppBotWebhookController
{
    public function receive(
        Request $request,
        WhatsAppWebhookService $webhook,
        CommunicationProviderGateway $gateway,
        VtuTransactionService $transactions,
        VtuPayloadValidator $validator
    ): Response {
        if (! Addon::query()->where('identifier', 'whatsapp.bot')->where('status', 'active')->exists()) {
            return response('WhatsApp bot is currently disabled', 503);
        }

        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');
        $providers = Provider::query()
            ->where('channel', 'whatsapp')
            ->where('enabled', true)
            ->where('paused', false)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($providers->isEmpty()) {
            return response('WhatsApp bot provider unavailable', 503);
        }

        // A shared webhook URL may serve multiple configured WhatsApp providers.
        // Match the signature first; never blindly validate against the first provider.
        $provider = $providers->first(
            fn (Provider $candidate): bool => $webhook->matchesSignature($candidate, $rawBody, $signature)
        );

        if (! $provider) {
            return response('Webhook rejected', 400);
        }

        try {
            $webhook->handle($provider, $rawBody, $signature);
        } catch (\\Throwable $e) {
            return response('Webhook rejected', 400);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response('Webhook rejected', 400);
        }

        foreach ($this->incomingMessages($payload) as $incoming) {
            $from = $this->canonicalPhone($incoming['from'] ?? null);
            $externalId = trim((string) ($incoming['id'] ?? ''));
            $bodyText = trim((string) ($incoming['body'] ?? ''));

            // Commands without a stable provider message ID cannot safely trigger
            // a transaction, because webhook retries could create duplicate purchases.
            if (! $from || $externalId === '' || $bodyText === '') {
                continue;
            }

            $commandKey = 'wa:' . hash('sha256', $from . '|' . $externalId);
            $replyKey = 'wa-bot:' . hash('sha256', $from . '|' . $externalId);

            // A duplicate inbound event must not execute a command twice. The stable
            // transaction key below also protects concurrent duplicate deliveries.
            if (Message::query()->where('channel', 'whatsapp')->where('idempotency_key', $replyKey)->exists()) {
                continue;
            }

            $user = User::query()->where('phone', $from)->first();
            $body = strtolower($bodyText);
            $conversation = Conversation::firstOrCreate(
                ['channel' => 'whatsapp', 'external_contact' => $from],
                ['user_id' => $user?->id, 'status' => 'open']
            );

            if (! $user || ! $user->whatsapp_verified_at || ! $user->whatsapp_transaction_enabled) {
                $reply = 'This WhatsApp number is not authorized for transactions. Register this number on SEMIZZY ONE, log in, then complete WhatsApp verification before transacting.';
            } elseif (in_array($body, ['hi', 'hello', 'menu', 'start'], true)) {
                $products = ServiceProduct::query()
                    ->with('service')
                    ->where('enabled', true)
                    ->whereHas('service', fn ($q) => $q->where('enabled', true))
                    ->orderBy('id')
                    ->limit(12)
                    ->get();

                $lines = $products->map(fn ($p) => '#' . $p->id . ' ' . $p->service->name . ' - ' . $p->name)->implode("\\n");
                $reply = "SEMIZZY ONE WhatsApp Services:\\n" . $lines . "\\n\\nPurchase format:\\nBUY product_id recipient amount PIN 1234\\nExample: BUY 12 08012345678 500 PIN 1234";
            } elseif (str_starts_with($body, 'buy ')) {
                $parts = preg_split('/\\\\s+/', trim($body));

                if (count($parts) < 5 || strtoupper($parts[count($parts) - 2]) !== 'PIN' || ! preg_match('/^\\\\d{4}$/', $parts[count($parts) - 1])) {
                    $reply = 'Invalid purchase format. Use: BUY product_id recipient amount PIN 1234';
                } elseif (! Hash::check($parts[count($parts) - 1], (string) $user->transaction_pin_hash)) {
                    $reply = 'Transaction PIN is invalid. Set or update your 4-digit transaction PIN on the website before using WhatsApp transactions.';
                } else {
                    try {
                        $productId = (int) $parts[1];
                        $recipient = $parts[2];
                        $amount = (float) $parts[3];
                        $product = ServiceProduct::query()->with('service')->findOrFail($productId);

                        abort_unless($product->enabled && $product->service?->enabled, 422, 'The selected service is currently unavailable.');

                        $transactionPayload = ['phone' => $recipient, 'recipient' => $recipient, 'amount' => $amount];
                        $validator->validate($product->service, $transactionPayload);
                        $tx = $transactions->create($user->id, $product, $transactionPayload, $user->role, $commandKey);
                        $tx = $transactions->process($tx);

                        $reply = 'Transaction ' . $tx->reference . ' has been submitted. Status: ' . strtoupper($tx->status) . '. You can continue receiving updates on WhatsApp.';
                    } catch (\\Throwable $e) {
                        $reply = 'Transaction was not submitted: ' . substr($e->getMessage(), 0, 180);
                    }
                }
            } else {
                $reply = 'I could not understand that command. Reply MENU to see services or use BUY product_id recipient amount PIN 1234.';
            }

            // Store the response once per provider message, independently of send
            // success. The Communication gateway owns transport and delivery attempts.
            $message = Message::query()->firstOrCreate(
                ['channel' => 'whatsapp', 'idempotency_key' => $replyKey],
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $user?->id,
                    'direction' => 'outbound',
                    'recipient' => $from,
                    'body' => $reply,
                    'status' => 'queued',
                    'metadata' => ['bot' => true, 'inbound_message_id' => $externalId],
                ]
            );

            if ($message->wasRecentlyCreated) {
                try {
                    $gateway->send($message);
                } catch (\\Throwable $e) {
                    // A send failure must never roll back a submitted financial transaction.
                }
            }
        }

        return response('OK', 200);
    }

    private function incomingMessages(array $payload): array
    {
        $messages = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                foreach (($value['messages'] ?? []) as $message) {
                    $messages[] = [
                        'id' => $message['id'] ?? null,
                        'from' => $message['from'] ?? null,
                        'body' => $message['text']['body']
                            ?? $message['button']['text']
                            ?? $message['interactive']['button_reply']['title']
                            ?? $message['interactive']['list_reply']['title']
                            ?? null,
                    ];
                }
            }
        }

        if ($messages === [] && isset($payload['message'])) {
            $messages[] = [
                'id' => $payload['id'] ?? $payload['message_id'] ?? null,
                'from' => $payload['from'] ?? $payload['sender'] ?? null,
                'body' => (string) $payload['message'],
            ];
        }

        return $messages;
    }

    private function canonicalPhone(?string $phone): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $phone);
        if ($phone === '') {
            return null;
        }
        if (str_starts_with($phone, '234')) {
            return '+' . $phone;
        }
        if (str_starts_with($phone, '0')) {
            return '+234' . substr($phone, 1);
        }

        return str_starts_with($phone, '+') ? $phone : null;
    }
}
