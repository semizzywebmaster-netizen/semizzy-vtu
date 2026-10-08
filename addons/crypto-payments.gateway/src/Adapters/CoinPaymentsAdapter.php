<?php

namespace Semizzy\Addons\CryptoPayments\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Semizzy\Addons\CryptoPayments\Contracts\CryptoPaymentGatewayAdapter;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use RuntimeException;

class CoinPaymentsAdapter implements CryptoPaymentGatewayAdapter
{
    public function __construct(private CryptoPaymentProvider $provider) {}

    private function request(string $command, array $fields = []): array
    {
        $credentials = $this->provider->credentials ?? [];
        $public = $credentials['public_key'] ?? $credentials['api_key'] ?? null;
        $private = $credentials['private_key'] ?? $credentials['secret_key'] ?? null;
        if (!$public || !$private) throw new RuntimeException('CoinPayments public/private API keys are not configured.');

        $data = array_merge(['version'=>1,'key'=>$public,'cmd'=>$command,'format'=>'json','nonce'=>time()], $fields);
        $body = http_build_query($data, '', '&');
        $signature = hash_hmac('sha512', $body, $private);

        $response = Http::asForm()->timeout(30)->retry(2,250,throw:false)
            ->withHeaders(['HMAC'=>$signature])
            ->post($this->provider->base_url ?: 'https://www.coinpayments.net/api.php', $data);
        $json = $response->json();
        if ($response->failed() || !is_array($json) || ($json['error'] ?? 'failed') !== 'ok') {
            throw new RuntimeException('CoinPayments API request failed: HTTP '.$response->status().' '.($json['error'] ?? ''));
        }
        return is_array($json['result'] ?? null) ? $json['result'] : [];
    }

    public function createPayment(array $payload): array
    {
        $result = $this->request('create_transaction', [
            'amount' => $payload['crypto_amount'] ?? 0,
            'currency1' => 'USD',
            'currency2' => strtoupper($payload['asset'] ?? 'BTC'),
            'buyer_email' => $payload['customer_email'] ?? '',
            'invoice' => $payload['reference'] ?? Str::uuid()->toString(),
            'item_name' => 'SEMIZZY ONE Crypto Payment',
        ]);
        return $result;
    }

    public function verifyPayment(array $payload): array
    {
        return $this->request('get_tx_info', ['txid' => $payload['payment_id'] ?? $payload['txid'] ?? '']);
    }

    public function createInvoice(array $payload): array { return $this->createPayment($payload); }
    public function createCheckout(array $payload): array { return $this->createPayment($payload); }
    public function payout(array $payload): array { throw new RuntimeException('CoinPayments payout requires a dedicated withdrawal policy.'); }
    public function refund(array $payload): array { throw new RuntimeException('CoinPayments refund requires provider-specific handling.'); }
    public function healthCheck(): array { return ['ok'=>true,'provider'=>$this->provider->code,'mode'=>'configured']; }
}
