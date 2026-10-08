<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Models\EducationTransaction;
final class EducationRequeryService {
 public function due(int $limit=100) {
  return EducationTransaction::query()->where('requery_required',true)->where('status','pending')->where(function($q){
   $q->whereNull('next_requery_at')->orWhere('next_requery_at','<=',now());
  })->orderBy('id')->limit($limit)->get();
 }
 public function schedule(EducationTransaction $transaction, int $minutes=5): EducationTransaction {
  $transaction->update(['status'=>'pending','requery_required'=>true,'next_requery_at'=>now()->addMinutes(max(1,$minutes))]);
  return $transaction->fresh();
 }
}