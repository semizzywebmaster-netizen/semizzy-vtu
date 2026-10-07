<?php
namespace Semizzy\Addons\GiftCards\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\GiftCards\Models\{GiftCardAttempt,GiftCardDelivery,GiftCardInventory,GiftCardOrder,GiftCardProduct};
class GiftCardPurchaseService {
 public function __construct(private GiftCardWalletService $wallet){}
 public function purchase(int $userId,int $productId,string $faceValue,string $currency,string $idempotency,array $requestData=[]): GiftCardOrder {
  $existing=GiftCardOrder::where('idempotency_key',$idempotency)->first(); if($existing)return $existing;
  return DB::transaction(function()use($userId,$productId,$faceValue,$currency,$idempotency,$requestData){
   $product=GiftCardProduct::with('provider')->whereKey($productId)->where('enabled',true)->lockForUpdate()->firstOrFail();
   $value=$this->money($faceValue); $faceCurrency=strtoupper((string)$product->currency);
   if(strtoupper($currency)!=='NGN')throw new RuntimeException('Only NGN wallet settlement is currently supported.');
   $denomination=$product->denominationOptions()->where('enabled',true)->where('face_currency',$faceCurrency)->whereRaw('CAST(face_value AS DECIMAL(20,2)) = ?',[$value])->lockForUpdate()->first();
   if($product->denomination_type==='fixed' && !$denomination)throw new RuntimeException('Selected denomination is not available.');
   if($product->denomination_type==='variable' && (($product->min_amount!==null&&bccomp($value,(string)$product->min_amount,2)<0)||($product->max_amount!==null&&bccomp($value,(string)$product->max_amount,2)>0)))throw new RuntimeException('Gift-card amount is outside the allowed range.');
   $total=$denomination?(string)$denomination->sale_price:(string)$product->sale_price; $total=$this->money($total);
   if($total==='0.00')throw new RuntimeException('Gift-card sale price is not configured.');
   $order=GiftCardOrder::create([
    'user_id'=>$userId,'gift_card_product_id'=>$product->id,'status'=>'processing','idempotency_key'=>$idempotency,
    'currency'=>'NGN','wallet_currency'=>'NGN','face_value'=>$value,'fee'=>'0.00','total'=>$total,
    'order_reference'=>'GC-'.strtoupper(Str::random(18)),'request_data'=>$requestData,
    'product_snapshot'=>['id'=>$product->id,'name'=>$product->name,'brand'=>$product->brand,'code'=>$product->code,'country_code'=>$product->country_code,'region'=>$product->region,'face_currency'=>$faceCurrency,'face_value'=>$value],
    'provider_snapshot'=>$product->provider?['id'=>$product->provider->id,'code'=>$product->provider->code,'name'=>$product->provider->name]:null
   ]);
   $this->wallet->reserve($order);
   if($product->fulfillment_mode==='inventory'){
    $card=GiftCardInventory::where('gift_card_product_id',$product->id)->where('status','available')->lockForUpdate()->first();
    if(!$card){$this->wallet->release($order);$order->update(['status'=>'failed','failure_reason'=>'No gift-card inventory is available.','failed_at'=>now()]);throw new RuntimeException('No gift-card inventory is available.');}
    $code=$card->code_encrypted; $pin=$card->pin_encrypted;
    GiftCardDelivery::create(['gift_card_order_id'=>$order->id,'code_encrypted'=>$code,'pin_encrypted'=>$pin,'code_fingerprint'=>hash('sha256',(string)$code)]);
    $card->update(['status'=>'sold','sold_at'=>now(),'sold_to_user_id'=>$userId]);
    $order->update(['status'=>'fulfilled','fulfilled_at'=>now()]);
    GiftCardAttempt::create(['gift_card_order_id'=>$order->id,'operation'=>'inventory_fulfillment','status'=>'accepted']);
    $this->wallet->settle($order);
   } else {
    GiftCardAttempt::create(['gift_card_order_id'=>$order->id,'provider_code'=>$product->provider?->code,'operation'=>'provider_purchase','status'=>'pending']);
    $order->update(['status'=>'provider_pending','provider_pending_at'=>now()]);
   }
   return $order->fresh(['product','delivery']);
  });
 }
 private function money(string $v):string{if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$v))throw new RuntimeException('Invalid monetary amount.');return number_format((float)$v,2,'.','');}
}