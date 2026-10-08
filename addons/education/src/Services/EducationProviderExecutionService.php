<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Models\EducationTransaction;
final class EducationProviderExecutionService {
 public function initiate(EducationTransaction $transaction): EducationTransaction {
  if ($transaction->status !== 'pending') return $transaction;
  if (!$transaction->provider_code) {
   $transaction->update(['status'=>'failed','failure_message'=>'No education provider was selected.','processed_at'=>now()]);
   return $transaction->fresh();
  }
  // Adapter execution is intentionally not fabricated here. Concrete provider adapters must use the Core provider contract.
  throw new \LogicException('Education provider adapter is not registered for '.$transaction->provider_code.'.');
 }
 public function markProviderAccepted(EducationTransaction $transaction, string $providerReference, array $providerData=[]): EducationTransaction {
  if ($transaction->status !== 'pending') return $transaction;
  $transaction->update(['provider_reference'=>$providerReference,'provider_data'=>$providerData,'status'=>'processing']);
  return $transaction->fresh();
 }
 public function markCompleted(EducationTransaction $transaction, array $providerData=[]): EducationTransaction {
  if (!in_array($transaction->status,['processing','pending'],true)) return $transaction;
  $transaction->update(['status'=>'successful','provider_data'=>$providerData,'processed_at'=>now(),'requery_required'=>false,'next_requery_at'=>null]);
  return $transaction->fresh();
 }
 public function markFailed(EducationTransaction $transaction, string $message, array $providerData=[]): EducationTransaction {
  if (in_array($transaction->status,['successful','failed'],true)) return $transaction;
  $transaction->update(['status'=>'failed','failure_message'=>$message,'provider_data'=>$providerData,'processed_at'=>now(),'requery_required'=>false]);
  return $transaction->fresh();
 }
 public function markPendingRequery(EducationTransaction $transaction, string $message='Provider outcome requires requery.'): EducationTransaction {
  if ($transaction->status==='successful') return $transaction;
  $transaction->update(['status'=>'pending','failure_message'=>$message,'requery_required'=>true,'next_requery_at'=>now()->addMinutes(5)]);
  return $transaction->fresh();
 }
}