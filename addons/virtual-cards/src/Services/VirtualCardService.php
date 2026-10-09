<?php
namespace Addons\VirtualCards\Services;
use Addons\VirtualCards\Models\{VirtualCard,VirtualCardTransaction};
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class VirtualCardService {
 public function request(int $userId,string $currency='NGN',array $metadata=[]): VirtualCard {
  $currency=strtoupper($currency);
  if(!preg_match('/^[A-Z]{3}$/',$currency)) throw ValidationException::withMessages(['currency'=>'Invalid card currency.']);
  return VirtualCard::create(['user_id'=>$userId,'status'=>'pending','card_type'=>'virtual','currency'=>$currency,'metadata'=>$metadata]);
 }
 public function attachIssuedCard(VirtualCard $card,string $providerKey,string $providerCardId,array $details=[]): VirtualCard {
  if($card->status==='terminated') throw ValidationException::withMessages(['card'=>'Terminated cards cannot be issued.']);
  $reference=$details['reference']??null;
  $card->update([
   'provider_key'=>$providerKey,'provider_card_id'=>$providerCardId,'status'=>'active',
   'brand'=>$details['brand']??$card->brand,'last4'=>$details['last4']??$card->last4,
   'expiry_month'=>$details['expiry_month']??$card->expiry_month,'expiry_year'=>$details['expiry_year']??$card->expiry_year,
   'encrypted_reference'=>$reference?Crypt::encryptString($reference):$card->encrypted_reference,
   'metadata'=>array_replace($card->metadata??[],$details['metadata']??[]),
  ]);
  return $card->fresh();
 }
 public function setSpendingLimit(VirtualCard $card,?int $limitMinor): VirtualCard {
  if($limitMinor!==null && $limitMinor<0) throw ValidationException::withMessages(['limit'=>'Limit cannot be negative.']);
  if($limitMinor!==null && $limitMinor<$card->spent_minor) throw ValidationException::withMessages(['limit'=>'Limit cannot be below already spent amount.']);
  $card->update(['spending_limit_minor'=>$limitMinor]); return $card->fresh();
 }
 public function setStatus(VirtualCard $card,string $status): VirtualCard {
  $allowed=['active','frozen','terminated'];
  if(!in_array($status,$allowed,true)) throw ValidationException::withMessages(['status'=>'Invalid card status.']);
  if($card->status==='terminated' && $status!=='terminated') throw ValidationException::withMessages(['status'=>'Terminated cards cannot be reactivated.']);
  $card->update(['status'=>$status]); return $card->fresh();
 }
 public function recordTransaction(VirtualCard $card,string $operationKey,int $amountMinor,string $currency,string $status='posted',array $data=[]): VirtualCardTransaction {
  if($amountMinor<=0) throw ValidationException::withMessages(['amount'=>'Amount must be greater than zero.']);
  $currency=strtoupper($currency);
  if($currency!==strtoupper($card->currency)) throw ValidationException::withMessages(['currency'=>'Transaction currency does not match the card.']);
  return DB::transaction(function() use($card,$operationKey,$amountMinor,$currency,$status,$data){
   $existing=VirtualCardTransaction::where('operation_key',$operationKey)->first();
   if($existing) return $existing;
   if($status==='posted' && $card->spending_limit_minor!==null && ($card->spent_minor+$amountMinor)>$card->spending_limit_minor)
    throw ValidationException::withMessages(['limit'=>'Virtual card spending limit exceeded.']);
   $tx=VirtualCardTransaction::create([
    'virtual_card_id'=>$card->id,'provider_transaction_id'=>$data['provider_transaction_id']??null,
    'operation_key'=>$operationKey,'status'=>$status,'currency'=>$currency,'amount_minor'=>$amountMinor,
    'merchant_name'=>$data['merchant_name']??null,'merchant_category'=>$data['merchant_category']??null,
    'occurred_at'=>$data['occurred_at']??now(),'metadata'=>$data['metadata']??[],
   ]);
   if($status==='posted') $card->increment('spent_minor',$amountMinor);
   return $tx;
  });
 }
}