<?php
namespace App\Services\Vtu;
use App\Models\FinancialOperation;
use App\Models\ServiceProduct;
use App\Models\VtuTransaction;
use App\Services\Audit\AuditLogger;
use App\Services\Pricing\PriceEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
class VtuTransactionService{
 public function __construct(private PriceEngine $pricing,private VtuProviderGateway $gateway,private VtuWalletService $wallet,private AuditLogger $audit){}
 public function quote(ServiceProduct $p,string $tier='USER'):array{return $this->pricing->quote($p,$this->normalizeTier($tier));}
 public function normalizeTier(string $role): string { $tier=strtoupper(trim($role)); return in_array($tier,['USER','AGENT','RESELLER','MERCHANT','CUSTOM'],true) ? $tier : 'USER'; }
 public function create(int $uid,ServiceProduct $p,array $payload,string $tier='USER',?string $key=null):VtuTransaction{$key=$key?:'vtu_'.Str::uuid();return DB::transaction(function()use($uid,$p,$payload,$tier,$key){$existing=VtuTransaction::query()->where('idempotency_key',$key)->lockForUpdate()->first();if($existing){if((int)$existing->user_id!==$uid||(int)$existing->service_product_id!==(int)$p->id||$existing->request_payload!==$payload)throw new RuntimeException('Idempotency key has already been used for a different transaction.');return $existing;}$q=$this->pricing->quote($p,$this->normalizeTier($tier));$minor=$this->toMinor($q['customer_price']);$ref='VTU-'.strtoupper(Str::random(20));$tx=VtuTransaction::create(['uuid'=>(string)Str::uuid(),'reference'=>$ref,'user_id'=>$uid,'service_id'=>$p->service_id,'service_product_id'=>$p->id,'api_provider_id'=>$q['provider_id'],'idempotency_key'=>$key,'status'=>'pending','amount_minor'=>$minor,'fee_minor'=>'0','total_minor'=>$minor,'currency'=>$q['currency'],'customer_tier'=>$this->normalizeTier($tier),'recipient'=>$this->recipient($payload),'request_payload'=>$payload,'metadata'=>['price_rule_id'=>$q['rule_id']]]);$op=FinancialOperation::create(['uuid'=>(string)Str::uuid(),'reference'=>$ref,'user_id'=>$uid,'type'=>'vtu.purchase','status'=>'pending','amount_minor'=>$minor,'currency'=>$q['currency'],'idempotency_key'=>$key,'metadata'=>['vtu_transaction_id'=>$tx->id]]);$tx->financial_operation_id=$op->id;$tx->save();$this->wallet->reserve($tx);$op->status='processing';$op->save();$tx->status='processing';$tx->processed_at=now();$tx->save();return $tx->fresh();});}
 public function process(VtuTransaction $tx):VtuTransaction{
  if($tx->isTerminal())return $tx;

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
  });
  if(!$claimed)return $tx->fresh();

  $r=$this->gateway->initiate($tx,$tx->request_payload??[]);
  return DB::transaction(function()use($tx,$r){$tx=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);if($tx->isTerminal())return $tx;$providerId=$r->providerId??$tx->api_provider_id;if($providerId!==null)$tx->api_provider_id=$providerId;$n=(int)$tx->attempts()->max('attempt_number')+1;$tx->attempts()->create(['api_provider_id'=>$providerId,'attempt_number'=>$n,'operation'=>'transaction_initiation','status'=>$r->status,'provider_reference'=>$r->providerReference,'request_payload'=>$tx->request_payload,'response_payload'=>is_array($r->data)?$r->data:null,'error_message'=>$r->message,'started_at'=>now(),'finished_at'=>now()]);$tx->provider_reference=$r->providerReference;$tx->provider_status=$r->status;$tx->response_payload=is_array($r->data)?$r->data:null;if($r->accepted){$tx->status='successful';$tx->completed_at=now();$this->finishFinancial($tx,true);}elseif($r->status==='PENDING'){$tx->status='pending';}elseif($r->status==='UNKNOWN'||$r->duplicateRisk){$tx->status='pending';$tx->failure_code='UNKNOWN_PROVIDER_STATE';$tx->failure_message='Provider state is uncertain; requery is required before retry or reversal.';}else{$tx->status='failed';$tx->failure_message=$r->message?:'Provider rejected the transaction.';$this->finishFinancial($tx,false);$tx->completed_at=now();}$tx->save();return $tx->fresh();});}
 public function requery(VtuTransaction $tx):VtuTransaction{if($tx->isTerminal())return $tx;if(!$tx->provider_reference)throw new RuntimeException('Cannot requery without a provider reference.');$r=$this->gateway->requery($tx);return DB::transaction(function()use($tx,$r){$tx=VtuTransaction::query()->lockForUpdate()->findOrFail($tx->id);if($tx->isTerminal())return $tx;$providerId=$r->providerId??$tx->api_provider_id;$n=(int)$tx->attempts()->max('attempt_number')+1;$tx->attempts()->create(['api_provider_id'=>$providerId,'attempt_number'=>$n,'operation'=>'transaction_status','status'=>$r->status,'provider_reference'=>$r->providerReference??$tx->provider_reference,'request_payload'=>['reference'=>$tx->provider_reference,'transaction_reference'=>$tx->reference],'response_payload'=>is_array($r->data)?$r->data:null,'error_message'=>$r->message,'started_at'=>now(),'finished_at'=>now()]);$tx->provider_status=$r->status;$tx->response_payload=is_array($r->data)?$r->data:$tx->response_payload;if($r->accepted){$tx->status='successful';$tx->completed_at=now();$this->finishFinancial($tx,true);}elseif($r->status==='UNKNOWN'||$r->duplicateRisk){$tx->status='pending';$tx->failure_code='UNKNOWN_PROVIDER_STATE';$tx->failure_message='Provider state is uncertain; requery is required before retry or reversal.';}elseif($r->status==='FAILED'){$tx->status='failed';$tx->failure_message=$r->message?:'Provider reports failure.';$tx->completed_at=now();$this->finishFinancial($tx,false);}else{$tx->status='pending';}$tx->save();return $tx->fresh();});}
 private function finishFinancial(VtuTransaction $tx,bool $success):void{$metadata=(array)$tx->metadata;if(($metadata['financial_settlement_applied']??false)===true){return;}$this->wallet->settle($tx,$success);$metadata['financial_settlement_applied']=true;$metadata['financial_settlement_applied_at']=now()->toIso8601String();$tx->metadata=$metadata;$op=$tx->financialOperation()->lockForUpdate()->first();if($op&&!in_array($op->status,['completed','failed','reversed'],true)){$op->status=$success?'completed':'failed';$op->provider_reference=$tx->provider_reference;$op->save();}$this->audit->record('vtu.transaction.'.($success?'successful':'failed'),$tx,['reference'=>$tx->reference,'provider_reference'=>$tx->provider_reference,'status'=>$tx->status]);}
 private function toMinor(string $a):string{if(!preg_match('/^\d+(?:\.\d{1,2})?$/',trim($a)))throw new RuntimeException('Invalid customer price.');[$x,$y]=array_pad(explode('.',trim($a),2),2,'0');$m=$x.str_pad(substr($y,0,2),2,'0');return ltrim($m,'0')?:'0';}
 private function recipient(array $p):?string{foreach(['phone','recipient','account_id','customer_number','meter_number','smartcard','source_phone'] as $k)if(isset($p[$k]))return (string)$p[$k];return null;}
}