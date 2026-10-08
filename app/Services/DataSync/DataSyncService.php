<?php
namespace App\Services\DataSync;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Semizzy\Addons\Education\Services\EducationOfficialInstitutionSyncService;
class DataSyncService {
 public function datasets(): array { return [
  ['dataset_key'=>'education.institutions','addon'=>'education','label'=>'Education institutions','adapter'=>'education.institutions','source_url'=>'NUC / NBTE / NCCE official catalogues','requires_review'=>true],
  ['dataset_key'=>'payments.banks.paystack.ng','addon'=>'payments','label'=>'Payment gateway bank list (Paystack Nigeria)','adapter'=>'payments.banks.paystack','source_url'=>'https://api.paystack.co/bank?currency=NGN','requires_review'=>false],
 ]; }
 public function syncAll(?User $user=null): array { $out=[]; foreach($this->datasets() as $dataset){ try{$out[$dataset['dataset_key']]=$this->sync($dataset['dataset_key'],$user);}catch(\Throwable $e){$out[$dataset['dataset_key']]=['dataset_key'=>$dataset['dataset_key'],'status'=>'failed','message'=>$e->getMessage()];} } return $out; }
 public function sync(string $key, ?User $user=null): array {
  $this->ensureDatasetSource($key);
  $dataset=collect($this->datasets())->firstWhere('dataset_key',$key); if(!$dataset) throw new InvalidArgumentException('Unknown synchronization dataset.');
  $started=now(); $runId=DB::table('data_sync_runs')->insertGetId(['dataset_key'=>$key,'status'=>'running','started_at'=>$started,'initiated_by'=>$user?->id,'created_at'=>$started,'updated_at'=>$started]);
  try { $result=match($dataset['adapter']){'education.institutions'=>$this->educationInstitutions(),'payments.banks.paystack'=>$this->paystackBanks()};
   DB::table('data_sync_runs')->where('id',$runId)->update(array_merge($result,['status'=>'completed','finished_at'=>now(),'updated_at'=>now()]));
   DB::table('data_sync_sources')->where('dataset_key',$key)->update(['last_synced_at'=>now(),'updated_at'=>now()]);
   return array_merge(['dataset_key'=>$key,'status'=>'completed','run_id'=>$runId],$result);
  } catch(\\Throwable $e){DB::table('data_sync_runs')->where('id',$runId)->update(['status'=>'failed','message'=>Str::limit($e->getMessage(),1000),'finished_at'=>now(),'updated_at'=>now()]); throw $e;}
 }
 private function ensureDatasetSource(string $key): void { $dataset=collect($this->datasets())->firstWhere('dataset_key',$key); if(!$dataset)return; DB::table('data_sync_sources')->updateOrInsert(['dataset_key'=>$key],['addon'=>$dataset['addon'],'label'=>$dataset['label'],'adapter'=>$dataset['adapter'],'source_url'=>$dataset['source_url']??null,'requires_review'=>$dataset['requires_review']??true,'enabled'=>true,'updated_at'=>now(),'created_at'=>now()]); }
 private function educationInstitutions(): array {
  $r=app(EducationOfficialInstitutionSyncService::class)->syncAll(); $total=['added'=>0,'updated'=>0,'unchanged'=>0,'review_required'=>0,'failed'=>0];
  foreach($r as $item){$i=(array)($item['import']??[]); $total['added']+=(int)($i['created']??0); $total['updated']+=(int)($i['updated']??0); $total['unchanged']+=(int)($i['skipped']??0);}
  $total['summary']=$r; return $total;
 }
 private function paystackBanks(): array {
  $secret=(string)config('services.paystack.secret',env('PAYSTACK_SECRET_KEY','')); if($secret==='') throw new RuntimeException('Paystack secret key is not configured.');
  $response=Http::withToken($secret)->acceptJson()->timeout(20)->retry(3,500)->get('https://api.paystack.co/bank',['currency'=>'NGN','perPage'=>100]);
  if(!$response->successful() || !$response->json('status')) throw new RuntimeException('Paystack bank catalogue request failed.');
  $added=$updated=$unchanged=0; foreach((array)$response->json('data',[]) as $bank){
   $payload=['name'=>(string)($bank['name']??''),'longcode'=>$bank['longcode']??null,'slug'=>$bank['slug']??null,'gateway'=>$bank['gateway']??null,'currency'=>$bank['currency']??'NGN','type'=>$bank['type']??null,'active'=>(bool)($bank['active']??true),'is_deleted'=>(bool)($bank['is_deleted']??false),'pay_with_bank'=>(bool)($bank['pay_with_bank']??false),'pay_with_bank_transfer'=>(bool)($bank['pay_with_bank_transfer']??false),'metadata'=>$bank,'source_updated_at'=>isset($bank['updatedAt'])?\Carbon\Carbon::parse($bank['updatedAt']):null,'updated_at'=>now()];
   $existing=DB::table('payment_banks')->where(['provider'=>'paystack','country'=>'NG','code'=>(string)$bank['code']])->first();
   if($existing){$before=(array)$existing; DB::table('payment_banks')->where('id',$existing->id)->update($payload); $changed=collect($payload)->some(fn($v,$k)=>array_key_exists($k,$before)&&$before[$k]!=$v); $changed?$updated++:$unchanged++;} else {DB::table('payment_banks')->insert(array_merge($payload,['provider'=>'paystack','country'=>'NG','code'=>(string)$bank['code'],'created_at'=>now()]));$added++;}
  }
  return compact('added','updated','unchanged')+['review_required'=>0,'failed'=>0,'summary'=>['count'=>count((array)$response->json('data',[]))]];
 }
}