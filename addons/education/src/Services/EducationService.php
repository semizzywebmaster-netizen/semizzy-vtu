<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Models\EducationProduct;
use Semizzy\Addons\Education\Models\EducationTransaction;
final class EducationService {
 public function __construct(private EducationTransactionService $transactions, private EducationProviderRouter $router, private EducationProviderExecutionService $execution, private EducationWalletService $wallet) {}
 public function begin(int $userId, EducationProduct $product, int $walletAccountId, int $amountMinor, array $customerData=[], ?string $clientReference=null): EducationTransaction {
  $tx=$this->transactions->create($userId,$product,$walletAccountId,$amountMinor,$customerData,$clientReference);
  try {
   $this->wallet->reserve($tx);
   $candidates=$this->router->candidates($product);
   if(!$candidates) return $this->failAndRelease($tx,'No active education provider is available.');
   $provider=$candidates[0]; $code=is_object($provider)&&isset($provider->code)?$provider->code:(is_array($provider)?($provider['code']??null):null);
   if(!$code) return $this->failAndRelease($tx,'Provider routing returned an invalid provider.');
   $tx->update(['provider_code'=>$code]);
   return $tx->fresh();
  } catch (\Throwable $e) {
   try { $this->wallet->release($tx); $tx->update(['status'=>'failed','failure_message'=>'Education transaction could not be started.','processed_at'=>now()]); }
   catch (\Throwable $releaseError) { $tx->update(['failure_message'=>'Education transaction could not be started and wallet release requires reconciliation.','requery_required'=>true,'next_requery_at'=>now()->addMinutes(5)]); }
   throw $e;
  }
 }
 public function complete(EducationTransaction $tx,array $providerData=[]): EducationTransaction {
  $tx=$this->execution->markCompleted($tx,$providerData); $this->wallet->settle($tx,true); return $tx->fresh();
 }
 public function fail(EducationTransaction $tx,string $message,array $providerData=[]): EducationTransaction {
  $tx=$this->execution->markFailed($tx,$message,$providerData); $this->wallet->release($tx); return $tx->fresh();
 }
 public function providerOutcomeUncertain(EducationTransaction $tx,string $message='Provider outcome requires requery.',array $providerData=[]): EducationTransaction {
  $tx=$this->execution->markPendingRequery($tx,$message); if($providerData){$tx->update(['provider_data'=>array_merge((array)$tx->provider_data,$providerData)]);} return $tx->fresh();
 }
 private function failAndRelease(EducationTransaction $tx,string $message): EducationTransaction { $tx->update(['status'=>'failed','failure_message'=>$message,'processed_at'=>now()]); $this->wallet->release($tx); return $tx->fresh(); }
}