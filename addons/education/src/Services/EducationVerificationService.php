<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Models\EducationProduct;
use Semizzy\Addons\Education\Models\EducationTransaction;
final class EducationVerificationService {
 public function validatePurchase(EducationProduct $product, array $data): array {
  if ($product->requires_institution && empty($data['institution_id'])) return ['valid'=>false,'message'=>'Institution is required.'];
  if ($product->requires_student_reference && trim((string)($data['student_reference']??''))==='') return ['valid'=>false,'message'=>'Student/application reference is required.'];
  if ($product->requires_session && trim((string)($data['session']??''))==='') return ['valid'=>false,'message'=>'Academic session is required.'];
  return ['valid'=>true,'message'=>null];
 }
 public function applyVerifiedData(EducationTransaction $transaction, array $verifiedData): EducationTransaction {
  $data=$transaction->customer_data ?? [];
  $data['verification']=$verifiedData;
  $transaction->update(['customer_data'=>$data]);
  return $transaction->fresh();
 }
}