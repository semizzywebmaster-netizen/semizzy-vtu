<?php

namespace Semizzy\Addons\Payments\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Semizzy\Addons\Payments\Models\PaymentIntent;

final class PaymentReconciliationService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function requery(PaymentIntent $payment): PaymentIntent
    {
        if (!$payment->provider_id) throw new RuntimeException('This payment has no assigned gateway provider.');
        $provider=PaymentGatewayProvider::query()->findOrFail($payment->provider_id);
        $reference=(string)($payment->provider_reference ?: $payment->reference);
        $verified=$this->gateways->adapter($provider)->verifyCollection($provider,$reference);
        $status=strtolower((string)match($provider->driver){
            'paystack'=>data_get($verified,'status',''),
            'monnify'=>data_get($verified,'paymentStatus',''),
            'opay'=>data_get($verified,'status',''),
            'kora'=>data_get($verified,'status',''),
            'squad'=>data_get($verified,'transaction_status',data_get($verified,'status','')),
            'flutterwave'=>data_get($verified,'status',''),
            default=>data_get($verified,'status',''),
        });
        $success=in_array($status,['success','successful','completed','complete','paid','approved'],true);
        $payment->forceFill(['metadata'=>array_merge((array)$payment->metadata,[
            'last_requery_at'=>now()->toISOString(),
            'last_requery_status'=>$status,
            'last_requery_provider'=>$provider->code,
        ])])->saveOrFail();
        if($success && $payment->status!=='paid'){
            throw new RuntimeException('Provider requery confirms payment success, but automatic wallet credit is intentionally blocked here. Use the verified webhook/reconciliation path to credit the Core wallet.');
        }
        return $payment->fresh();
    }

    public function requestRefund(PaymentIntent $payment,array $payload=[]): array
    {
        if(!$payment->provider_id) throw new RuntimeException('This payment has no assigned gateway provider.');
        if($payment->status!=='paid') throw new RuntimeException('Only paid payments can be refunded.');
        if($payment->refunded_at) throw new RuntimeException('This payment is already marked refunded.');
        $provider=PaymentGatewayProvider::query()->findOrFail($payment->provider_id);
        if(!$provider->supports('refund') || !app(PaymentGatewayAdapterRegistry::class)->has($provider->driver)){
            throw new RuntimeException('The assigned provider does not expose a verified refund capability.');
        }
        $result=$this->gateways->adapter($provider)->refund($provider,array_merge([
            'amount_minor'=>(string)$payment->amount_minor,
            'currency'=>$payment->currency,
            'original_reference'=>$payment->provider_reference ?: $payment->reference,
            'reference'=>'REF-'.$payment->reference,
        ],$payload));
        $payment->forceFill(['metadata'=>array_merge((array)$payment->metadata,[
            'refund_requested_at'=>now()->toISOString(),
            'refund_provider'=>$provider->code,
            'refund_result'=>$result,
            'refund_accounting_status'=>'pending_core_wallet_reversal',
        ])])->saveOrFail();
        return $result;
    }
}
