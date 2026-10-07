<?php
namespace Semizzy\Addons\Escrow\Services;
use App\Models\Addon;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Escrow\Models\EscrowDispute;
use Semizzy\Addons\Escrow\Models\EscrowTransaction;

final class EscrowService {
 private function sub(string $a,string $b):string{return function_exists('bcsub')?bcsub($a,$b,0):(string)((int)$a-(int)$b);}
 private function add(string $a,string $b):string{return function_exists('bcadd')?bcadd($a,$b,0):(string)((int)$a+(int)$b);}
 private function gte(string $a,string $b):bool{return function_exists('bccomp')?bccomp($a,$b,0)>=0:(int)$a>=(int)$b;}
 private function settings(): array {
  $s=Addon::query()->where('identifier','escrow.protection')->value('settings_schema') ?? [];
  return collect(is_array($s)?$s:[])->mapWithKeys(fn($x)=>[($x['key']??'')=>$x['default']??null])->all();
 }
 private function lockWallets(int $buyerId,int $sellerId): array {
  $ids=[$buyerId,$sellerId]; sort($ids,SORT_NUMERIC);
  return WalletAccount::whereIn('user_id',$ids)->where('currency','NGN')->lockForUpdate()->get()->keyBy('user_id')->all();
 }
 private function movement(WalletAccount $wallet,string $key,string $ref,string $type,string $amount,string $before,string $after,string $currency,array $meta):void {
  WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>$key,'reference'=>$ref,'type'=>$type,'amount_minor'=>$amount,'currency'=>$currency,'available_before_minor'=>$before,'available_after_minor'=>$after,'held_before_minor'=>$wallet->held_minor,'held_after_minor'=>$wallet->held_minor,'metadata'=>$meta]);
 }
 public function create(int $buyerId,string $sellerQuery,string $amountMinor,string $title,?string $description, string $idempotencyKey):EscrowTransaction {
  if(!preg_match('/^\d+$/',$amountMinor)||!$this->gte($amountMinor,'1')) throw new RuntimeException('Escrow amount must be positive.');
  $s=$this->settings(); $currency=strtoupper((string)($s['default_currency']??'NGN')); $max=(string)($s['max_amount_minor']??'1000000000'); $fee=(string)($s['fee_minor']??'0');
  if($currency!=='NGN') throw new RuntimeException('Escrow currently supports NGN only.');
  if(!$this->gte($max,$amountMinor)) throw new RuntimeException('Escrow amount exceeds the configured limit.');
  if(!preg_match('/^\d+$/',$fee)||!preg_match('/^\d+$/',$max)) throw new RuntimeException('Escrow financial settings are invalid.');
  $idempotencyKey=trim($idempotencyKey); if($idempotencyKey==='') throw new RuntimeException('A valid idempotency key is required.');
  try{return DB::transaction(function()use($buyerId,$sellerQuery,$amountMinor,$title,$description,$idempotencyKey,$currency,$fee,$s){
   $existing=EscrowTransaction::where('buyer_id',$buyerId)->where('idempotency_key',$idempotencyKey)->lockForUpdate()->first();
   if($existing){ if((string)$existing->amount_minor===$amountMinor) return $existing; throw new RuntimeException('This idempotency key has already been used for a different escrow.');}
   $q=trim($sellerQuery); $seller=User::query()->where(fn($x)=>$x->where('username',$q)->orWhere('email',$q)->orWhere('phone',$q))->first();
   if(!$seller) throw new RuntimeException('Seller account not found.'); if($seller->id===$buyerId) throw new RuntimeException('You cannot create escrow with yourself as seller.');
   $wallets=$this->lockWallets($buyerId,$seller->id); $buyer=$wallets[$buyerId]??null;
   if(!$buyer||$buyer->status!=='active') throw new RuntimeException('Buyer must have an active NGN wallet.');
   $total=$this->add($amountMinor,$fee); if(!$this->gte((string)$buyer->available_minor,$total)) throw new RuntimeException('Insufficient wallet balance.');
   $beforeAvailable=(string)$buyer->available_minor; $beforeHeld=(string)$buyer->held_minor;
   $buyer->available_minor=$this->sub($beforeAvailable,$total); $buyer->held_minor=$this->add($beforeHeld,$total); $buyer->save();
   $hours=max(1,(int)($s['default_expiry_hours']??72)); $tx=EscrowTransaction::create(['buyer_id'=>$buyerId,'seller_id'=>$seller->id,'reference'=>'ESC-'.strtoupper(Str::random(20)),'idempotency_key'=>$idempotencyKey,'currency'=>$currency,'amount_minor'=>$amountMinor,'fee_minor'=>$fee,'status'=>'funded','title'=>trim($title),'description'=>$description,'expires_at'=>now()->addHours($hours),'funded_at'=>now(),'metadata'=>['addon'=>'escrow.protection']]);
   $this->movement($buyer,'escrow:hold:'.$tx->id,$tx->reference,'hold',$total,$beforeAvailable,$buyer->available_minor,$currency,['addon'=>'escrow.protection','escrow_id'=>$tx->id]);
   return $tx->fresh();
  });}catch(QueryException $e){if(str_contains(strtolower($e->getMessage()),'unique')||str_contains(strtolower($e->getMessage()),'duplicate')){$x=EscrowTransaction::where('buyer_id',$buyerId)->where('idempotency_key',$idempotencyKey)->first();if($x)return $x;}throw $e;}
 }
 public function release(int $userId,int $id):EscrowTransaction {
  return DB::transaction(function()use($userId,$id){
   $tx=EscrowTransaction::lockForUpdate()->findOrFail($id); if($tx->buyer_id!==$userId) throw new RuntimeException('Only the buyer can release escrow.');
   if(!in_array($tx->status,['funded','disputed'],true)) throw new RuntimeException('This escrow cannot be released in its current state.');
   if($tx->status==='disputed') throw new RuntimeException('A disputed escrow must be resolved by an administrator.');
   $wallets=$this->lockWallets($tx->buyer_id,$tx->seller_id); $buyer=$wallets[$tx->buyer_id]??null; $seller=$wallets[$tx->seller_id]??null;
   if(!$buyer||!$seller) throw new RuntimeException('Both wallets are required.');
   $amount=(string)$tx->amount_minor; $beforeSeller=(string)$seller->available_minor; $beforeHeld=(string)$buyer->held_minor;
   if(!$this->gte($beforeHeld,$amount)) throw new RuntimeException('Escrow hold balance is inconsistent.');
   $buyer->held_minor=$this->sub($beforeHeld,$amount); $seller->available_minor=$this->add((string)$seller->available_minor,$amount); $buyer->save();$seller->save();
   $this->movement($seller,'escrow:release:credit:'.$tx->id,$tx->reference,'credit',$amount,$beforeSeller,$seller->available_minor,$tx->currency,['addon'=>'escrow.protection','escrow_id'=>$tx->id]);
   $tx->status='released';$tx->released_at=now();$tx->save(); return $tx->fresh();
  });
 }
 public function cancel(int $buyerId,int $id):EscrowTransaction {
  return DB::transaction(function()use($buyerId,$id){
   $tx=EscrowTransaction::lockForUpdate()->findOrFail($id); if($tx->buyer_id!==$buyerId) throw new RuntimeException('Only the buyer can cancel escrow.');
   if($tx->status!=='funded') throw new RuntimeException('Only funded escrow can be cancelled.');
   $wallet=WalletAccount::where('user_id',$tx->buyer_id)->where('currency','NGN')->lockForUpdate()->first(); if(!$wallet)throw new RuntimeException('Buyer wallet not found.');
   $amount=$this->add((string)$tx->amount_minor,(string)$tx->fee_minor);$beforeHeld=(string)$wallet->held_minor;
   if(!$this->gte($beforeHeld,$amount))throw new RuntimeException('Escrow hold balance is inconsistent.');
   $wallet->held_minor=$this->sub($beforeHeld,$amount);$wallet->available_minor=$this->add((string)$wallet->available_minor,$amount);$wallet->save();
   $afterAvailable=(string)$wallet->available_minor; $this->movement($wallet,'escrow:cancel:release:'.$tx->id,$tx->reference,'credit',$amount,$this->sub($afterAvailable,$amount),$afterAvailable,$tx->currency,['addon'=>'escrow.protection','escrow_id'=>$tx->id]);
   $tx->status='cancelled';$tx->cancelled_at=now();$tx->save();return $tx->fresh();
  });
 }
 public function dispute(int $userId,int $id,string $reason,?string $details):EscrowDispute {
  return DB::transaction(function()use($userId,$id,$reason,$details){
   $tx=EscrowTransaction::lockForUpdate()->findOrFail($id); if(!in_array($userId,[$tx->buyer_id,$tx->seller_id],true))throw new RuntimeException('Only the buyer or seller can dispute this escrow.');
   if($tx->status!=='funded')throw new RuntimeException('Only funded escrow can be disputed.');
   $d=EscrowDispute::create(['escrow_transaction_id'=>$tx->id,'opened_by'=>$userId,'reason'=>trim($reason),'details'=>$details,'status'=>'open']);$tx->status='disputed';$tx->disputed_at=now();$tx->save();return $d->fresh();
  });
 }
 public function resolve(int $adminId,int $id,string $decision,?string $note):EscrowTransaction {
  return DB::transaction(function()use($adminId,$id,$decision,$note){
   $tx=EscrowTransaction::lockForUpdate()->findOrFail($id); if($tx->status!=='disputed')throw new RuntimeException('Only disputed escrow can be resolved.');
   $wallets=$this->lockWallets($tx->buyer_id,$tx->seller_id);$buyer=$wallets[$tx->buyer_id]??null;$seller=$wallets[$tx->seller_id]??null;if(!$buyer||!$seller)throw new RuntimeException('Both wallets are required.');
   $amount=(string)$tx->amount_minor;
   if($decision==='release'){ $before=(string)$seller->available_minor;$beforeHeld=(string)$buyer->held_minor;if(!$this->gte($beforeHeld,$amount))throw new RuntimeException('Escrow hold balance is inconsistent.');$buyer->held_minor=$this->sub($beforeHeld,$amount);$seller->available_minor=$this->add($before,$amount);$buyer->save();$seller->save();$this->movement($seller,'escrow:resolve:release:'.$tx->id,$tx->reference,'credit',$amount,$before,$seller->available_minor,$tx->currency,['addon'=>'escrow.protection','escrow_id'=>$tx->id]);$tx->status='released';$tx->released_at=now();}
   elseif($decision==='refund'){ $before=(string)$buyer->available_minor;$beforeHeld=(string)$buyer->held_minor;$total=$this->add($amount,(string)$tx->fee_minor);if(!$this->gte($beforeHeld,$total))throw new RuntimeException('Escrow hold balance is inconsistent.');$buyer->held_minor=$this->sub($beforeHeld,$total);$buyer->available_minor=$this->add($before,$total);$buyer->save();$this->movement($buyer,'escrow:resolve:refund:'.$tx->id,$tx->reference,'credit',$total,$before,$buyer->available_minor,$tx->currency,['addon'=>'escrow.protection','escrow_id'=>$tx->id]);$tx->status='refunded';$tx->refunded_at=now();}
   else throw new RuntimeException('Resolution must be release or refund.');
   $tx->metadata=array_merge((array)$tx->metadata,['resolution'=>['admin_id'=>$adminId,'decision'=>$decision,'note'=>$note]]);$tx->save();
   EscrowDispute::where('escrow_transaction_id',$tx->id)->where('status','open')->update(['status'=>'resolved','resolved_by'=>$adminId,'resolved_at'=>now(),'resolution_note'=>$note]);
   return $tx->fresh();
  });
 }
}