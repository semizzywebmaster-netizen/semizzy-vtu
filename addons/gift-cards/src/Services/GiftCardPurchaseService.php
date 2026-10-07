<?php
namespace Semizzy\Addons\GiftCards\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\GiftCards\Models\{GiftCardAttempt,GiftCardInventory,GiftCardOrder,GiftCardProduct};

class GiftCardPurchaseService
{
 public function __construct(private GiftCardWalletService $wallet){}
 public function purchase(int $userId,int $productId,string $faceValue,string $currency,string $idempotency,array $requestData=[]): GiftCardOrder
 {
  $existing=GiftCardOrder::where('idempotency_key',$idempotency)->first();
  if($existing) return $existing;
  return DB::transaction(function() use($userId,$productId,$faceValue,$currency,$idempotency,$requestData){
   $product=GiftCardProduct::whereKey($productId)->where('enabled',true)->lockForUpdate()->firstOrFail();
   $value=$this->money($faceValue);
   if($product->denomination_type==='fixed' && !in_array($value,array_map('strval',$product->denominations??[]),true)) throw new RuntimeException('Selected denomination is not available.');
   if($product->denomination_type==='variable' && (($product->min_amount!==null && bccomp($value,(string)$product->min_amount,2)<0) || ($product->max_amount!==null && bccomp($value,(string)$product->max_amount,2)>0))) throw new RuntimeException('Gift-card amount is outside the allowed range.');
   if(strtoupper($currency)!=='NGN') throw new RuntimeException('Wallet currency is not supported for this purchase.');
   $total=$this->money((string)$product->sale_price);
   if($product->denomination_type==='variable') $total=$value;
   $order=GiftCardOrder::create(['user_id'=>$userId,'gift_card_product_id'=>$product->id,'status'=>'processing','idempotency_key'=>$idempotency,'currency'=>'NGN','face_value'=>$value,'fee'=>$this->money((string)max(0,(float)$total-(float)$value)),'total'=>$total,'order_reference'=>'GC-'.strtoupper(Str::random(18)),'request_data'=>$requestData]);
   $this->wallet->reserve($order);
   if($product->fulfillment_mode==='inventory'){
    $card=GiftCardInventory::where('gift_card_product_id',$product->id)->where('status','available')->lockForUpdate()->first();
    if(!$card){$this->wallet->release($order);$order->update(['status'=>'failed','failure_reason'=>'No gift-card inventory is available.','failed_at'=>now()]);throw new RuntimeException('No gift-card inventory is available.');}
    $card->update(['status'=>'sold','sold_at'=>now(),'sold_to_user_id'=>$userId]);
    $order->update(['status'=>'fulfilled','delivery_data'=>['inventory_id'=>$card->id,'code'=>$card->code_encrypted,'pin'=>$card->pin_encrypted],'fulfilled_at'=>now()]);
    GiftCardAttempt::create(['gift_card_order_id'=>$order->id,'operation'=>'inventory_fulfillment','status'=>'accepted']);
    $this->wallet->settle($order);
   } else {
    GiftCardAttempt::create(['gift_card_order_id'=>$order->id,'operation'=>'provider_purchase','status'=>'pending']);
    $order->update(['status'=>'provider_pending']);
   }
   return $order->fresh();
  });
 }
 private function money(string $v): string { if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$v)) throw new RuntimeException('Invalid monetary amount.'); return number_format((float)$v,2,'.',''); }
}