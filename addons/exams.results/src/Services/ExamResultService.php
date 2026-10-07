<?php
namespace Semizzy\Addons\Exams\Services;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Services\Providers\ProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Exams\Models\ExamProduct;
use Semizzy\Addons\Exams\Models\ExamTransaction;
final class ExamResultService {
 public function __construct(private ProviderManager $providers){}
 private function sub(string $a,string $b):string{return function_exists('bcsub')?bcsub($a,$b,0):(string)((int)$a-(int)$b);}
 private function add(string $a,string $b):string{return function_exists('bcadd')?bcadd($a,$b,0):(string)((int)$a+(int)$b);}
 private function gte(string $a,string $b):bool{return function_exists('bccomp')?bccomp($a,$b,0)>=0:(int)$a>=(int)$b;}
 private function wallet(int $userId,string $currency):WalletAccount{
  $w=WalletAccount::where('user_id',$userId)->where('currency',strtoupper($currency))->lockForUpdate()->first();
  if(!$w||$w->status!=='active')throw new RuntimeException('Active wallet not found.');
  return $w;
 }
 public function check(int $userId,int $productId,string $identifier,?string $candidateName,string $idempotencyKey):ExamTransaction{
  return DB::transaction(function()use($userId,$productId,$identifier,$candidateName,$idempotencyKey){
   $existing=ExamTransaction::where('user_id',$userId)->where('idempotency_key',$idempotencyKey)->lockForUpdate()->first();
   if($existing)return $existing;
   $product=ExamProduct::whereKey($productId)->where('active',true)->lockForUpdate()->firstOrFail();
   $attempts=ExamTransaction::where('user_id',$userId)->where('product_id',$product->id)->where('candidate_identifier',$identifier)->where('status','!=','failed')->lockForUpdate()->count();
   if($attempts >= (int)$product->max_attempts)throw new RuntimeException('Maximum result-check attempts reached for this candidate.');
   $wallet=$this->wallet($userId,$product->currency);$amount=(string)$product->price_minor;
   if(!$this->gte((string)$wallet->available_minor,$amount))throw new RuntimeException('Insufficient wallet balance.');
   $before=(string)$wallet->available_minor;$wallet->available_minor=$this->sub($before,$amount);$wallet->save();
   $reference='EXM-'.strtoupper(Str::random(20));
   $tx=ExamTransaction::create(['user_id'=>$userId,'product_id'=>$product->id,'reference'=>$reference,'candidate_identifier'=>$identifier,'candidate_name'=>$candidateName,'status'=>'processing','amount_minor'=>$amount,'currency'=>$product->currency,'idempotency_key'=>$idempotencyKey]);
   WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>'exam:debit:'.$tx->id,'reference'=>$reference,'type'=>'debit','amount_minor'=>$amount,'currency'=>$wallet->currency,'available_before_minor'=>$before,'available_after_minor'=>$wallet->available_minor,'held_before_minor'=>$wallet->held_minor,'held_after_minor'=>$wallet->held_minor,'metadata'=>['addon'=>'exams.results','transaction_id'=>$tx->id]]);
   try{$result=$this->providers->execute('exams.results','transaction_initiation',['reference'=>$reference,'product_key'=>$product->key,'exam_body'=>$product->exam_body,'candidate_identifier'=>$identifier,'candidate_name'=>$candidateName],$reference);}
   catch(\Throwable $e){$tx->forceFill(['status'=>'unknown','error'=>'Provider execution could not be completed.'])->save();return $tx->fresh();}
   $tx->forceFill(['provider_reference'=>$result->providerReference,'status'=>strtolower($result->status),'result_payload'=>is_array($result->data)?$result->data:null,'error'=>$result->message])->save();
   if($result->accepted||$result->duplicateRisk||in_array(strtoupper($result->status),['UNKNOWN','PENDING','PROCESSING'],true))return $tx->fresh();
   $this->refund($tx,$wallet,$result->message?:'Provider rejected result check.');$tx->forceFill(['status'=>'failed'])->save();return $tx->fresh();
  });
 }
 public function refund(ExamTransaction $tx,WalletAccount $wallet,string $reason):void{
  $op='exam:refund:'.$tx->id;if(WalletMovement::where('operation_key',$op)->exists())return;
  $before=(string)$wallet->available_minor;$after=$this->add($before,(string)$tx->amount_minor);$wallet->available_minor=$after;$wallet->save();
  WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>$op,'reference'=>$tx->reference,'type'=>'refund','amount_minor'=>$tx->amount_minor,'currency'=>$wallet->currency,'available_before_minor'=>$before,'available_after_minor'=>$after,'held_before_minor'=>$wallet->held_minor,'held_after_minor'=>$wallet->held_minor,'metadata'=>['addon'=>'exams.results','reason'=>$reason]]);
 }
 public function reconcile():int{
  $count=0;ExamTransaction::whereIn('status',['pending','processing','unknown'])->whereNotNull('provider_reference')->chunkById(50,function($items)use(&$count){
   foreach($items as $item){
    DB::transaction(function()use($item,&$count){
     $tx=ExamTransaction::whereKey($item->id)->lockForUpdate()->first();
     if(!$tx||!in_array($tx->status,['pending','processing','unknown'],true)||!$tx->provider_reference)return;
     try{$result=$this->providers->execute('exams.results','transaction_status',['reference'=>$tx->reference,'provider_reference'=>$tx->provider_reference],$tx->reference.':status');}catch(\\Throwable){return;}
     $status=strtoupper($result->status);
     if(in_array($status,['SUCCESS','SUCCESSFUL','COMPLETED','DELIVERED'],true)){$tx->update(['status'=>'successful','result_payload'=>is_array($result->data)?$result->data:$tx->result_payload,'error'=>null]);$count++;return;}
     if(in_array($status,['FAILED','REJECTED','CANCELLED'],true)){$wallet=WalletAccount::where('user_id',$tx->user_id)->where('currency',$tx->currency)->lockForUpdate()->first();if($wallet)$this->refund($tx,$wallet,'Provider reconciliation failure.');$tx->update(['status'=>'failed']);$count++;}
    });
   }
  });return $count;
 }
}