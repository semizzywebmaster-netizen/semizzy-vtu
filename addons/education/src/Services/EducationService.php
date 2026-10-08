<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Models\EducationProduct;
use Semizzy\Addons\Education\Models\EducationTransaction;
final class EducationService {
 public function __construct(
  private EducationTransactionService $transactions,
  private EducationProviderRouter $router,
  private EducationProviderExecutionService $execution
 ) {}
 public function begin(int $userId, EducationProduct $product, int $walletAccountId, int $amountMinor, array $customerData=[], ?string $clientReference=null): EducationTransaction {
  $tx=$this->transactions->create($userId,$product,$walletAccountId,$amountMinor,$customerData,$clientReference);
  $candidates=$this->router->candidates($product);
  if (!$candidates) {
   $tx->update(['status'=>'failed','failure_message'=>'No active education provider is available.','processed_at'=>now()]);
   return $tx->fresh();
  }
  $provider=$candidates[0];
  $code=is_object($provider)&&isset($provider->code)?$provider->code:(is_array($provider)?($provider['code']??null):null);
  if (!$code) {
   $tx->update(['status'=>'failed','failure_message'=>'Provider routing returned an invalid provider.','processed_at'=>now()]);
   return $tx->fresh();
  }
  $tx->update(['provider_code'=>$code]);
  return $tx->fresh();
 }
}