<?php

namespace Semizzy\Addons\CryptoPayments\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Semizzy\Addons\CryptoPayments\Contracts\CryptoPaymentGatewayAdapter;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use RuntimeException;

class BinancePayAdapter implements CryptoPaymentGatewayAdapter
{
    public function __construct(private CryptoPaymentProvider $provider) {}

    private function request(string $path, array $payload = []): array
    {
        $credentials = $this->provider->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? $credentials['certificate_sn'] ?? null;
        $secret = $credentials['secret_key'] ?? null;

        if (!$apiKey || !$secret) throw new RuntimeException('Binance Pay API key/certificate and secret are not configured.');

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) round(microtime(true) * 1000);
        $nonce = Str::random(32);
        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $signature = strtoupper(hash_hmac('sha512', $message, $secret));

        $response = Http::baseUrl(rtrim($this->provider->base_url ?: 'https://bpay.binanceapi.com', '/'))
            ->acceptJson()->asJson()->timeout(30)->retry(2, 250, throw: false)
            ->withHeaders([
                'BinancePay-Timestamp' => $timestamp,
                'BinancePay-Nonce' => $nonce,
                'BinancePay-Certificate-SN' => $apiKey,
                'BinancePay-Signature' => $signature,
            ])->post($path, $payload);

        $data = $response->json();
        if ($response->failed() || !is_array($data) || ($data['status'] ?? 'FAIL') !== 'SUCCESS') {
            throw new RuntimeException('Binance Pay API request failed: HTTP '.$response->status().' '.($data['errorMessage'] ?? ''));
        }
        return $data['data'] ?? $data;
    }

    public function createPayment(array $payload): array
    {
        $tradeNo = substr(preg_replace('/[^A-Za-z0-9]/', '', (string)($payload['reference'] ?? 'CRP'.uniqid())), 0, 32);
        return $this->request('/binancepay/openapi/order', [
            'merchantTradeNo' => $tradeNo,
            'orderAmount' => (float)($payload['fiat_amount'] ?? 0),
            'currency' => strtoupper((string)($payload['asset'] ?? 'USDT')),
            'description' => substr((string)($payload['order_description'] ?? 'SEMIZZY ONE Crypto Payment'), 0, 256),
            'goods' => ['goodsType'=>'02','goodsCategory'=>'Z000','referenceGoodsId'=>$tradeNo,'goodsName'=>'Crypto Payment'],
            'passThroughInfo' => (string)($payload['reference'] ?? ''),
        ]);
    }

    public function verifyPayment(array $payload): array
    {
        return $this->request('/binancepay/openapi/order/query', [
            'merchantTradeNo' => $payload['merchant_trade_no'] ?? $payload['reference'] ?? null,
            'prepayId' => $payload['prepay_id'] ?? null,
        ]);
    }

    public function createInvoice(array $payload): array { return $this->createPayment($payload); }
    public function createCheckout(array $payload): array { return $this->createPayment($payload); }
    public function payout(array $payload): array { throw new RuntimeException('Binance Pay payout is not enabled by this adapter.'); }
    public function refund(array $payload): array { throw new RuntimeException('Binance Pay refund requires a dedicated refund flow.'); }

    public function healthCheck(): array
    {
        return ['ok' => true, 'provider' => $this->provider->code, 'mode' => 'configured'];
    }
}
