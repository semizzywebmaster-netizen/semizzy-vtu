<?php

namespace Semizzy\Addons\CryptoPayments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use Semizzy\Addons\CryptoPayments\Services\CryptoPaymentWebhookService;

class CryptoPaymentWebhookController
{
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $model = CryptoPaymentProvider::where('code', $provider)->firstOrFail();
        app(CryptoPaymentWebhookService::class)->process($model, $request);

        return response()->json(['ok' => true]);
    }
}
