<?php

namespace Semizzy\Addons\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Semizzy\Addons\Payments\Models\PaymentIntent;
use Semizzy\Addons\Payments\Services\PaymentService;

class PaymentController extends Controller
{
    public function status(Request $request, string $reference): JsonResponse
    {
        $payment = PaymentIntent::where('user_id', $request->user()->id)->where('reference', $reference)->firstOrFail();

        return response()->json([
            'reference' => $payment->reference,
            'status' => $payment->status,
            'currency' => $payment->currency,
            'amount_minor' => $payment->amount_minor,
            'provider_reference' => $payment->provider_reference,
            'checkout_url' => $payment->checkout_url,
            'expires_at' => $payment->expires_at?->toISOString(),
            'paid_at' => $payment->paid_at?->toISOString(),
        ]);
    }

    public function create(Request $request, PaymentService $payments): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'amount_minor' => ['required','integer','min:1'],
            'currency' => ['nullable','string','size:3'],
        ])->validate();

        $wallet = $request->user()->walletAccounts()->where('currency', strtoupper($data['currency'] ?? 'NGN'))->where('status', 'active')->firstOrFail();
        $payment = $payments->createFundingIntent($request->user()->id, $wallet, (string) $data['amount_minor'], strtoupper($data['currency'] ?? 'NGN'));

        return response()->json($payment->only(['reference','status','currency','amount_minor','provider_reference','checkout_url','expires_at']));
    }
}
