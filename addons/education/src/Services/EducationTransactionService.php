<?php
namespace Semizzy\Addons\Education\Services;
use Illuminate\Support\Str;
use Semizzy\Addons\Education\Models\EducationProduct;
use Semizzy\Addons\Education\Models\EducationTransaction;
final class EducationTransactionService {
 public function create(int $userId, EducationProduct $product, int $walletAccountId, int $amountMinor, array $customerData=[], ?string $clientReference=null): EducationTransaction {
  if (!$product->active) throw new \RuntimeException('Education product is unavailable.');
  if ($amountMinor < 1) throw new \InvalidArgumentException('Amount must be positive.');
  $clientReference ??= 'EDU-'.Str::upper(Str::random(20));
  return EducationTransaction::create([
   'uuid'=>(string) Str::uuid(),'user_id'=>$userId,'education_product_id'=>$product->id,
   'institution_id'=>$product->institution_id,'wallet_account_id'=>$walletAccountId,
   'client_reference'=>$clientReference,'status'=>'pending','currency'=>$product->currency,
   'amount_minor'=>$amountMinor,'provider_amount_minor'=>$product->provider_amount_minor,
   'customer_data'=>$customerData,
  ]);
 }
}