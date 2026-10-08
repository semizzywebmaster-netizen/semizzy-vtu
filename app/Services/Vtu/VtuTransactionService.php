<?php
namespace App\Services\Vtu;
use App\Models\Addon;
use App\Models\FinancialOperation;
use App\Models\ServiceProduct;
use App\Models\VtuTransaction;
use App\Models\VtuBulkOperationItem;
use App\Services\Audit\AuditLogger;
use App\Services\Pricing\PriceEngine;
use App\Services\Commercial\CommercialServiceRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
class VtuTransactionService{
 public function __construct(private PriceEngine $pricing,private VtuProviderGateway $gateway,private VtuWalletService $wallet,private AuditLogger $audit,private CommercialServiceRegistry $commercial){}
 public function quote(ServiceProduct $p,string $tier='USER'):array{
  $tier=$this->normalizeTier($tier);
  $quote=$this->pricing->quote($p,$tier);
  $details=$this->commercial->quoteDetails((int)auth()->id(),(string)$p->service?->key,(string)$p->key,(int)$this->toMinor($quote['customer_price']));
  $quote['customer_price']=number_format(((int)$details['amount_minor'])/100,2,'.','');
  $quote['business_partner_id']=$details['partner_id']??null;
  return $quote;
 }
 public function normalizeTier(string $role): string { $tier=strtoupper(trim($role)); return in_array($tier,['USER','AGENT','RESELLER','MERCHANT','CUSTOM'],true) ? $tier : 'USER'; }
 public function create(int $uid,ServiceProduct $p,array $payload,string $tier='USER',?string $key=null):VtuTransaction{$p->loadMissing('service.category');if(!$p->service||$p->service->category?->key!=='vtu-digital-services')throw new RuntimeException('Only products belonging to the active VTU & Digital Services addon can be transacted.');if(!$p->service->enabled)throw new RuntimeException('This service is currently unavailable. Please try again later.');if(!Addon::query()->where('identifier','vtu.digital-services')->where('status','active')->exists())throw new RuntimeException('The VTU & Digital Services addon is not active.');$key=$key?:'vtu_'.Str::uuid();return DB::transaction(function()use($uid,$p,$payload,$tier,$key){$existing=VtuTransaction::query()->where('idempotency_key',$key)->lockForUpdate()->first();if($existing){if((int)$existing->user_id!==$uid||(int)$existing->service_product_id!==(int)$p->id||$existing->request_payload!==$payload)throw new RuntimeException('Idempotency key has already been used for a different transaction.');return $existing;}$normalizedTier=$this->normalizeTier($tier);$q=$this->pricing->quote($p,$normalizedTier);$baseMinor=(int)$this->toMinor($q['customer_price']);$details=$this->commercial->quoteDetails($uid,(string)$p->service?->key,(string)$p->key,$baseMinor);$minor=(int)$details['amount_minor'];$this->commercial->authorize($uid,(string)$p->service?->key,(string)$p->key,$minor);$ref='VTU-'.strtoupper(Str::random(20));$tx=VtuTransaction::create(['uuid'=>(string)Str::uuid(),'reference'=>$ref,'user_id'=>$uid,'service_id'=>$p->service_id,'service_product_id'=>$p->id,'api_provider_id'=>$q['provider_id'],'idempotency_key'=>$key,'status'=>'pending','amount_minor'=>$minor,'fee_minor'=>'0','total_minor'=>$minor,'currency'=>$q['currency'],'customer_tier'=>$normalizedTier,'recipient'=>$this->recipient($payload),'request_payload'=>$payload,'metadata'=>['price_rule_id'=>$q['rule_id'],'business_partner_id'=>$details['partner_id']??null,'base_customer_price_minor'=>$baseMinor,'commercial_price_rule_id'=>$details['rule_id']??null]]);$op=FinancialOperation::create(['uuid'=>(string)Str::uuid(),'reference'=>$ref,'user_id'=>$uid,'type'=>'vtu.purchase','status'=>'pending','amount_minor'=>$minor,'currency'=>$q['currency'],'idempotency_key'=>$key,'metadata'=>['vtu_transaction_id'=>$tx->id]]);$tx->financial_operation_id=$op->id;$tx->save();$this->wallet->reserve($tx);$op->status='processing';$op->save();$tx->status='processing';$tx->processed_at=now();$tx->save();return $tx->fresh();});}
 public function process(VtuTransaction $tx):VtuTransaction{
  if($tx->isTerminal())return $tx;
  $tx->loadMissing('service');
  if($tx->service && !$tx->service->enabled){
   $tx->failure_code='SERVICE_DISABLED';
   $tx->failure_message='The service is currently disabled by an administrator. Execution will resume when the service is enabled.';
   return $tx->fresh();
  }

  // Claim initiation durably before any provider network I/O. A concurrent
  // worker that sees the claim must not initiate the same transaction again.
  $claimed=DB::transaction(function()use($tx):bool{
    $locked=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
    if($locked->isTerminal())return false;
    $metadata=(array)$locked->metadata;
    if(($metadata['provider_initiation_claimed']??false)===true)return false;
    $metadata['provider_initiation_claimed']=true;
    $metadata['provider_initiation_claimed_at']=now()->toIso8601String();
    $locked->metadata=$metadata;
    $locked->save();
    $tx->setRawAttributes($locked->getAttributes());
    $tx->exists=true;
    return true;
  }, 5);
  if(!$claimed)return $tx->fresh();

  $r=$this->gateway->initiate($tx,$tx->request_payload??[]);
  return DB::transaction(function()use($tx,$r){$tx=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);if($tx->isTerminal())return $tx;$providerId=$r->providerId??$tx->api_provider_id;if($providerId!==null)$tx->api_provider_id=$providerId;$n=(int)$tx->attempts()->max('attempt_number')+1;$tx->attempts()->create(['api_provider_id'=>$providerId,'attempt_number'=>$n,'operation'=>'transaction_initiation','status'=>$r->status,'provider_reference'=>$r->providerReference,'request_payload'=>$tx->request_payload,'response_payload'=>is_array($r->data)?$r->data:null,'error_message'=>$r->message,'started_at'=>now(),'finished_at'=>now()]);$tx->provider_reference=$r->providerReference;$tx->provider_status=$r->status;$tx->response_payload=is_array($r->data)?$r->data:null;if($r->accepted){$tx->status='successful';$tx->completed_at=now();$this->finishFinancial($tx,true);}elseif($r->status==='PENDING'){$tx->status='pending';}elseif($r->status==='UNKNOWN'||$r->duplicateRisk){$tx->status='pending';$tx->failure_code='UNKNOWN_PROVIDER_STATE';$tx->failure_message='Provider state is uncertain; requery is required before retry or reversal.';}else{$tx->status='failed';$tx->failure_message=$r->message?:'Provider rejected the transaction.';$this->finishFinancial($tx,false);$tx->completed_at=now();}$tx->save();$this->syncBulkState($tx);return $tx->fresh();});}
 public function cancelPending(VtuTransaction $tx,string $reason='Cancelled by administrator'):VtuTransaction
 {
  return DB::transaction(function()use($tx,$reason){
   $locked=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
   if($locked->isTerminal())return $locked;
   $metadata=(array)$locked->metadata;
   if(($metadata['provider_initiation_claimed']??false)===true || $locked->provider_reference){
    throw new RuntimeException('This transaction has already entered provider processing and cannot be safely cancelled. Requery is required.');
   }
   $this->wallet->cancelReservation($locked);
   $locked->status='cancelled';
   $locked->failure_code='CANCELLED';
   $locked->failure_message=$reason;
   $locked->completed_at=now();
   $metadata['cancelled_at']=now()->toIso8601String();
   $metadata['cancellation_reason']=$reason;
   $metadata['financial_settlement_applied']=true;
   $metadata['financial_settlement_applied_at']=now()->toIso8601String();
   $locked->metadata=$metadata;
   $op=$locked->financialOperation()->lockForUpdate()->first();
   if($op && !in_array($op->status,['completed','reversed'],true)){
    $op->status='failed';
    $op->metadata=array_merge((array)$op->metadata,['cancelled'=>true,'cancellation_reason'=>$reason]);
    $op->save();
   }
   $locked->save();
   $this->audit->record('vtu.transaction.cancelled',$locked,['reference'=>$locked->reference,'reason'=>$reason,'status'=>'cancelled']);
   $this->syncBulkState($locked);
   return $locked->fresh();
  });
 }

 public function requery(VtuTransaction $tx):VtuTransaction{
  if($tx->isTerminal())return $tx;
  if(!$tx->provider_reference)throw new RuntimeException('Cannot requery without a provider reference.');
  $claim=DB::transaction(function()use($tx):?string{
    $locked=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
    if($locked->isTerminal()||!$locked->provider_reference)return null;
    $metadata=(array)$locked->metadata;
    $leaseUntil=$metadata['requery_claim_until']??null;
    if($leaseUntil&&now()->lt(\Illuminate\Support\Carbon::parse($leaseUntil)))return null;
    $token=(string)Str::uuid();
    $metadata['requery_claim_token']=$token;
    $metadata['requery_claimed_at']=now()->toIso8601String();
    $metadata['requery_claim_until']=now()->addMinutes(5)->toIso8601String();
    $locked->metadata=$metadata;
    $locked->save();
    return $token;
  });
  if($claim===null)return $tx->fresh();
  try{$r=$this->gateway->requery($tx);}catch(\Throwable $e){
    DB::transaction(function()use($tx,$claim){
      $locked=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
      $metadata=(array)$locked->metadata;
      if(($metadata['requery_claim_token']??null)===$claim){
        unset($metadata['requery_claim_token'],$metadata['requery_claimed_at'],$metadata['requery_claim_until']);
        $locked->metadata=$metadata;
        $locked->failure_code='UNKNOWN_PROVIDER_STATE';
        $locked->failure_message='Provider requery failed; transaction remains pending until the next reconciliation attempt.';
        $locked->save();
      }
    });
    throw $e;
  }
  return DB::transaction(function()use($tx,$r,$claim){
    $tx=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
    if($tx->isTerminal())return $tx;
    $metadata=(array)$tx->metadata;
    if(($metadata['requery_claim_token']??null)!==$claim)return $tx;
    unset($metadata['requery_claim_token'],$metadata['requery_claimed_at'],$metadata['requery_claim_until']);
    $tx->metadata=$metadata;
    $providerId=$r->providerId??$tx->api_provider_id;
    $n=(int)$tx->attempts()->max('attempt_number')+1;
    $tx->attempts()->create(['api_provider_id'=>$providerId,'attempt_number'=>$n,'operation'=>'transaction_status','status'=>$r->status,'provider_reference'=>$r->providerReference??$tx->provider_reference,'request_payload'=>['reference'=>$tx->provider_reference,'transaction_reference'=>$tx->reference],'response_payload'=>is_array($r->data)?$r->data:null,'error_message'=>$r->message,'started_at'=>now(),'finished_at'=>now()]);
    $tx->provider_status=$r->status;
    if($r->providerReference!==null)$tx->provider_reference=$r->providerReference;
    $tx->response_payload=is_array($r->data)?$r->data:$tx->response_payload;
    if($r->accepted){$tx->status='successful';$tx->completed_at=now();$this->finishFinancial($tx,true);}
    elseif($r->status==='UNKNOWN'||$r->duplicateRisk){$tx->status='pending';$tx->failure_code='UNKNOWN_PROVIDER_STATE';$tx->failure_message='Provider state is uncertain; requery is required before retry or reversal.';}
    elseif($r->status==='FAILED'){$tx->status='failed';$tx->failure_message=$r->message?:'Provider reports failure.';$tx->completed_at=now();$this->finishFinancial($tx,false);}
    else{$tx->status='pending';}
    $tx->save();
    $this->syncBulkState($tx);
    return $tx->fresh();
  });
 }

 public function refund(VtuTransaction $tx, string $reason = 'Administrative refund'): VtuTransaction
 {
  $claim=DB::transaction(function()use($tx,$reason):?string{
   $locked=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
   if($locked->status==='reversed')return null;
   if($locked->status!=='successful')throw new RuntimeException('Only a successful VTU transaction can be refunded.');
   $metadata=(array)$locked->metadata;
   if(($metadata['refund_settlement_applied']??false)===true)return null;
   // A provider refund that timed out or was accepted while local settlement
   // failed is a reconciliation case. Never allow a second provider refund.
   if(($metadata['refund_manual_resolution_required']??false)===true
      || ($metadata['refund_pending']??false)===true
      || ($metadata['refund_provider_accepted']??false)===true) {
    throw new RuntimeException('Refund is pending reconciliation; automatic retry is blocked.');
   }
   if(isset($metadata['refund_claim_token']))return null;
   $token=(string)Str::uuid();
   $metadata['refund_claim_token']=$token;
   $metadata['refund_claimed_at']=now()->toIso8601String();
   $metadata['refund_reason']=$reason;
   $locked->metadata=$metadata;
   $locked->save();
   return $token;
  });
  if($claim===null)return $tx->fresh();

  try {
   $r=$this->gateway->refund($tx,$reason);
  } catch (\Throwable $e) {
   // A timeout/error after the provider request may still mean the refund was
   // accepted. Keep the transaction successful, but permanently block an
   // automatic second refund until reconciliation confirms the provider state.
   return DB::transaction(function()use($tx,$claim,$reason,$e){
    $locked=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
    $metadata=(array)$locked->metadata;
    if(($metadata['refund_claim_token']??null)===$claim){
     unset($metadata['refund_claim_token'],$metadata['refund_claimed_at']);
     $metadata['refund_pending']=true;
     $metadata['refund_manual_resolution_required']=true;
     $metadata['refund_provider_status']='UNKNOWN';
     $metadata['refund_reason']=$reason;
     $metadata['refund_error']=$e->getMessage();
     $locked->metadata=$metadata;
     $locked->failure_code='REFUND_PROVIDER_STATE_UNKNOWN';
     $locked->failure_message='Provider refund state is uncertain; no wallet credit or retry is allowed until reconciliation.';
     $locked->save();
    }
    return $locked->fresh();
   });
  }

  return DB::transaction(function()use($tx,$r,$claim,$reason){
   $locked=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
   $metadata=(array)$locked->metadata;
   if(($metadata['refund_claim_token']??null)!==$claim)return $locked->fresh();

   $providerId=$r->providerId??$locked->api_provider_id;
   $n=(int)$locked->attempts()->max('attempt_number')+1;
   $locked->attempts()->create([
    'api_provider_id'=>$providerId,
    'attempt_number'=>$n,
    'operation'=>'refund',
    'status'=>$r->status,
    'provider_reference'=>$r->providerReference??$locked->provider_reference,
    'request_payload'=>[
     'reference'=>$locked->provider_reference,
     'transaction_reference'=>$locked->reference,
     'amount_minor'=>$locked->total_minor,
     'currency'=>$locked->currency,
     'reason'=>$reason,
    ],
    'response_payload'=>is_array($r->data)?$r->data:null,
    'error_message'=>$r->message,
    'started_at'=>now(),
    'finished_at'=>now(),
   ]);

   if($r->accepted){
    try {
     $this->wallet->creditRefund($locked);
    } catch (\Throwable $e) {
     // The provider has already accepted the refund. Never release the claim
     // or allow an automatic second refund when local wallet settlement fails.
     $metadata['refund_provider_accepted']=true;
     $metadata['refund_provider_accepted_at']=now()->toIso8601String();
     $metadata['refund_provider_reference']=$r->providerReference;
     $metadata['refund_settlement_pending']=true;
     $metadata['refund_manual_resolution_required']=true;
     $metadata['refund_settlement_error']=$e->getMessage();
     unset($metadata['refund_claim_token'],$metadata['refund_claimed_at']);
     $locked->metadata=$metadata;
     $locked->provider_status=$r->status;
     $locked->failure_code='REFUND_SETTLEMENT_PENDING';
     $locked->failure_message='Provider accepted the refund, but local wallet settlement failed. Manual reconciliation is required; automatic retry is blocked.';
     $locked->save();
     return $locked->fresh();
    }
    $metadata['refund_settlement_applied']=true;
    $metadata['refund_settlement_applied_at']=now()->toIso8601String();
    $metadata['refund_provider_reference']=$r->providerReference;
    unset($metadata['refund_claim_token'],$metadata['refund_claimed_at']);
    $locked->metadata=$metadata;
    $locked->provider_status=$r->status;
    $locked->status='reversed';
    $locked->failure_code=null;
    $locked->failure_message=null;
    $locked->completed_at=now();
    $op=$locked->financialOperation()->lockForUpdate()->first();
    if($op && $op->status==='completed'){
     $op->status='reversed';
     $op->metadata=array_merge((array)$op->metadata,['refund_reference'=>$r->providerReference,'refund_reason'=>$reason]);
     $op->save();
    }
    $locked->save();
    $this->audit->record('vtu.transaction.refunded',$locked,['reference'=>$locked->reference,'provider_reference'=>$locked->provider_reference,'refund_provider_reference'=>$r->providerReference,'reason'=>$reason,'status'=>'reversed']);
    $this->syncBulkState($locked);
    return $locked->fresh();
   }

   $locked->provider_status=$r->status;
   if($r->providerReference!==null)$locked->provider_reference=$r->providerReference;
   if($r->status==='UNKNOWN'||$r->duplicateRisk||$r->status==='PENDING'){
    $metadata['refund_pending']=true;
    $metadata['refund_provider_status']=$r->status;
    $metadata['refund_manual_resolution_required']=true;
    unset($metadata['refund_claim_token'],$metadata['refund_claimed_at']);
    $locked->failure_code='REFUND_PROVIDER_STATE_UNKNOWN';
    $locked->failure_message='Provider refund state is uncertain; no wallet credit or retry is allowed until reconciliation.';
    $locked->metadata=$metadata;
   }else{
    unset($metadata['refund_claim_token'],$metadata['refund_claimed_at']);
    $metadata['refund_failed_at']=now()->toIso8601String();
    $locked->metadata=$metadata;
    $locked->failure_code='REFUND_FAILED';
    $locked->failure_message=$r->message?:'Provider rejected the refund.';
   }
   $locked->save();
   return $locked->fresh();
  });
 }

 public function recoverStaleInitiationClaim(VtuTransaction $tx, int $staleMinutes = 10): VtuTransaction
 {
  return DB::transaction(function()use($tx,$staleMinutes){
   $locked=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);
   if($locked->isTerminal())return $locked;
   $metadata=(array)$locked->metadata;
   if(($metadata['provider_initiation_claimed']??false)!==true)return $locked;
   $claimedAt=$metadata['provider_initiation_claimed_at']??null;
   if(!$claimedAt || now()->lt(\Illuminate\Support\Carbon::parse($claimedAt)->addMinutes($staleMinutes)))return $locked;
   if($locked->provider_reference)return $locked;
   $metadata['provider_initiation_recovery_at']=now()->toIso8601String();
   $metadata['manual_provider_resolution_required']=true;
   $locked->metadata=$metadata;
   $locked->status='pending';
   $locked->failure_code='UNKNOWN_PROVIDER_STATE';
   $locked->failure_message='Provider initiation did not finish durably. No automatic retry is allowed; provider state requires reconciliation or manual resolution.';
   $locked->save();
   $this->syncBulkState($locked);
   return $locked->fresh();
  });
 }
 private function syncBulkState(VtuTransaction $tx):void{$item=VtuBulkOperationItem::query()->where('vtu_transaction_id',$tx->id)->first();if(!$item)return;$item->update(['status'=>$tx->status,'amount_minor'=>$tx->total_minor,'error_message'=>$tx->failure_message]);$bulk=$item->bulk()->lockForUpdate()->first();if(!$bulk)return;$successful=$bulk->items()->where('status','successful')->count();$failed=$bulk->items()->whereIn('status',['failed','reversed'])->count();$cancelled=$bulk->items()->where('status','cancelled')->count();$pending=max(0,$bulk->total_items-$successful-$failed-$cancelled);$bulk->successful_items=$successful;$bulk->failed_items=$failed;$bulk->processed_items=$successful+$failed+$cancelled;$bulk->status=match(true){$pending>0=>'pending',$cancelled===$bulk->total_items=>'cancelled',$failed>0&&($successful>0||$cancelled>0)=>'partial',$cancelled>0&&$successful>0=>'partial',$failed>0=>'failed',default=>'successful'};$bulk->metadata=array_merge((array)$bulk->metadata,['pending_items'=>$pending]);$bulk->save();}
 private function finishFinancial(VtuTransaction $tx,bool $success):void{$metadata=(array)$tx->metadata;if(($metadata['financial_settlement_applied']??false)===true){return;}$this->wallet->settle($tx,$success);if($success&&($metadata['commercial_usage_recorded']??false)!==true){$tx->loadMissing('service','product');$this->commercial->record($tx->user_id,(string)$tx->service?->key,(string)$tx->product?->key,(int)$tx->total_minor,(string)$tx->id);$metadata['commercial_usage_recorded']=true;$metadata['commercial_usage_recorded_at']=now()->toIso8601String();}$metadata['financial_settlement_applied']=true;$metadata['financial_settlement_applied_at']=now()->toIso8601String();$tx->metadata=$metadata;$op=$tx->financialOperation()->lockForUpdate()->first();if($op&&!in_array($op->status,['completed','failed','reversed'],true)){$op->status=$success?'completed':'failed';$op->provider_reference=$tx->provider_reference;$op->save();}$this->audit->record('vtu.transaction.'.($success?'successful':'failed'),$tx,['reference'=>$tx->reference,'provider_reference'=>$tx->provider_reference,'status'=>$tx->status]);}
 private function toMinor(string $a):string{if(!preg_match('/^\d+(?:\.\d{1,2})?$/',trim($a)))throw new RuntimeException('Invalid customer price.');[$x,$y]=array_pad(explode('.',trim($a),2),2,'0');$m=$x.str_pad(substr($y,0,2),2,'0');return ltrim($m,'0')?:'0';}
 private function recipient(array $p):?string{foreach(['phone','recipient','account_id','customer_number','meter_number','smartcard','source_phone'] as $k)if(isset($p[$k]))return (string)$p[$k];return null;}
}