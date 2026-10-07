<?php
namespace Semizzy\Addons\Government\Services;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\Government\Models\GovernmentService;
use Semizzy\Addons\Government\Models\GovernmentApplication;
use Semizzy\Addons\Government\Models\GovernmentDocument;
use App\Services\Providers\ProviderManager;

class GovernmentServicesService {
 public function __construct(private ProviderManager $providers){}
 public function createApplication(int $userId,GovernmentService $service,array $data=[]): GovernmentApplication {
  if($service->status!=='active') throw new RuntimeException('Government service is unavailable.');
  return DB::transaction(function()use($userId,$service,$data){return GovernmentApplication::create([
   'reference'=>'GOV-'.strtoupper(Str::random(12)),'user_id'=>$userId,'service_id'=>$service->id,'status'=>'draft',
   'amount'=>$service->price,'currency'=>$service->currency,'payment_status'=>'unpaid',
   'application_data'=>$data,'metadata'=>['fulfillment_mode'=>$service->fulfillment_mode],
  ]);});
 }
 public function addDocument(GovernmentApplication $application,array $fileData): GovernmentDocument {
  if(in_array($application->status,['completed','failed','cancelled'],true)) throw new RuntimeException('This application no longer accepts documents.');
  return GovernmentDocument::create(['application_id'=>$application->id,'document_type'=>$fileData['document_type'],'disk'=>$fileData['disk']??'local','path'=>$fileData['path'],'original_name'=>$fileData['original_name']??null,'mime_type'=>$fileData['mime_type']??null,'size_bytes'=>$fileData['size_bytes']??null,'status'=>'pending','metadata'=>$fileData['metadata']??[]]);
 }
 public function payFromWallet(\App\Models\User $user,GovernmentApplication $application): GovernmentApplication {
  return DB::transaction(function()use($user,$application){
   $a=GovernmentApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
   if((int)$a->user_id!==$user->id) throw new RuntimeException('Application ownership mismatch.');
   if($a->payment_status==='paid') return $a->fresh();
   if(!in_array($a->status,['draft','awaiting_documents','pending_payment'],true)) throw new RuntimeException('Application is not awaiting payment.');
   $wallet=\App\Models\WalletAccount::query()->where('user_id',$user->id)->where('currency',$a->currency)->lockForUpdate()->first();
   if(!$wallet||$wallet->status!=='active') throw new RuntimeException('Active wallet not found.');
   $amount=$this->toMinor((string)$a->amount);$before=(string)$wallet->available_minor;
   if($this->compare($before,$amount)<0) throw new RuntimeException('Insufficient wallet balance.');
   $after=$this->subtract($before,$amount);$wallet->available_minor=$after;$wallet->saveOrFail();
   $ref='GOV-P-'.Str::upper(Str::random(24));
   \App\Models\WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>'government:payment:'.$a->reference,'reference'=>$ref,'type'=>'government_service_payment','amount_minor'=>$amount,'currency'=>$wallet->currency,'available_before_minor'=>$before,'available_after_minor'=>$after,'held_before_minor'=>(string)$wallet->held_minor,'held_after_minor'=>(string)$wallet->held_minor,'metadata'=>['application_id'=>$a->id,'application_reference'=>$a->reference]]);
   $a->payment_reference=$ref;$a->payment_status='paid';$a->paid_at=now();$a->status='paid';$a->save();return $a->fresh();
  });
 }
 public function submitToProvider(GovernmentApplication $application): GovernmentApplication {
  if($application->payment_status!=='paid') throw new RuntimeException('Application must be paid before provider submission.');
  if($application->service->fulfillment_mode==='manual') throw new RuntimeException('This service is configured for manual fulfillment.');
  $payload=['reference'=>$application->reference,'service_key'=>$application->service->service_key,'application_data'=>$application->application_data,'metadata'=>$application->metadata];
  $result=$this->providers->execute($application->service->service_key,'government_application_submit',$payload,$application->reference);
  $status=strtoupper((string)$result->status);
  $application->provider_id=$result->providerId?:$application->provider_id;$application->provider_reference=$result->providerReference?:$application->provider_reference;
  $application->metadata=array_merge((array)$application->metadata,['provider_status'=>$status,'provider_message'=>$result->message,'submitted_provider_at'=>now()->toIso8601String()]);
  if($result->accepted||in_array($status,['SUCCESS','SUCCESSFUL','ACCEPTED','COMPLETED'],true)){$application->status='submitted';$application->submitted_at=$application->submitted_at?:now();}
  elseif(in_array($status,['FAILED','REJECTED','INVALID'],true))$application->status='failed';
  else $application->status='processing';
  $application->save();return $application->fresh();
 }
 public function requery(GovernmentApplication $application): GovernmentApplication {
  if(!$application->provider_id||!$application->provider_reference) throw new RuntimeException('Provider reference is unavailable for requery.');
  $provider=\App\Models\ApiProvider::query()->whereKey((int)$application->provider_id)->firstOrFail();
  $result=$this->providers->executeProvider($provider,$application->service->service_key,'government_application_status',['reference'=>$application->reference,'provider_reference'=>$application->provider_reference,'application_data'=>$application->application_data,'metadata'=>$application->metadata],$application->reference.':status');
  $status=strtoupper((string)$result->status);$application->metadata=array_merge((array)$application->metadata,['last_requery_status'=>$status,'last_requery_message'=>$result->message,'last_requery_at'=>now()->toIso8601String()]);
  if($result->accepted||in_array($status,['SUCCESS','SUCCESSFUL','COMPLETED','VERIFIED'],true)){$application->status='completed';$application->completed_at=$application->completed_at?:now();}
  elseif(in_array($status,['FAILED','REJECTED','INVALID','CANCELLED','EXPIRED'],true))$application->status='failed';
  elseif(in_array($status,['UNKNOWN','PENDING','PROCESSING'],true))$application->status='processing';
  $application->save();return $application->fresh();
 }
 public function submitApplication(GovernmentApplication $application,bool $requireDocumentReview=true): GovernmentApplication {
  if(!in_array($application->status,['draft','awaiting_documents','paid'],true))throw new RuntimeException('Application cannot be submitted from its current status.');
  $documents=$application->documents()->get();
  if($documents->isEmpty()&&$requireDocumentReview){$application->update(['status'=>'awaiting_documents']);return $application->fresh();}
  if($requireDocumentReview&&$documents->contains(fn($d)=>$d->status!=='approved')){$application->update(['status'=>'awaiting_documents']);return $application->fresh();}
  $application->update(['status'=>'submitted','submitted_at'=>now()]);return $application->fresh();
 }
 private function toMinor(string $major): string {if(!preg_match('/^\d+(?:\.\d{1,2})?$/',trim($major)))throw new RuntimeException('Invalid application amount.');[$w,$f]=array_pad(explode('.',trim($major),2),2,'');$m=ltrim($w.str_pad($f,2,'0'),'0')?:'0';if($m==='0')throw new RuntimeException('Application amount must be positive.');return $m;}
 private function compare(string $a,string $b): int {$a=ltrim($a,'0')?:'0';$b=ltrim($b,'0')?:'0';return strlen($a)<=>strlen($b)?:strcmp($a,$b);}
 private function subtract(string $a,string $b): string {if(function_exists('bcsub'))return bcsub($a,$b,0);if(strlen(ltrim($a,'0')?:'0')>17)throw new RuntimeException('Large wallet amounts require BCMath.');return(string)((int)$a-(int)$b);}
}