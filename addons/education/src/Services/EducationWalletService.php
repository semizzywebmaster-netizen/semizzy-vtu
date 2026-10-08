<?php
namespace Semizzy\Addons\Education\Services;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Semizzy\Addons\Education\Models\EducationTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class EducationWalletService {
 public function reserve(EducationTransaction $tx): void {
  DB::transaction(function() use($tx): void {
   $wallet=$this->wallet($tx); $key="education:{$tx->id}:reserve";
   if(WalletMovement::query()->where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
   $amount=(string)$tx->amount_minor; $available=(string)$wallet->available_minor; $held=(string)$wallet->held_minor;
   if($this->cmp($available,$amount)<0) throw new RuntimeException('Insufficient wallet balance.');
   $wallet->available_minor=$this->sub($available,$amount); $wallet->held_minor=$this->add($held,$amount); $wallet->save();
   $this->movement($wallet,$key,$tx,'reserve',$amount,$available,(string)$wallet->available_minor,$held,(string)$wallet->held_minor);
  });
 }
 public function settle(EducationTransaction $tx,bool $success): void {
  DB::transaction(function() use($tx,$success): void {
   $wallet=$this->wallet($tx); $key="education:{$tx->id}:settle:".($success?'success':'failure');
   if(WalletMovement::query()->where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
   $opposite="education:{$tx->id}:settle:".($success?'failure':'success');
   if(WalletMovement::query()->where('wallet_account_id',$wallet->id)->where('operation_key',$opposite)->exists()) throw new RuntimeException('Wallet settlement already finalized with the opposite outcome.');
   $amount=(string)$tx->amount_minor; $available=(string)$wallet->available_minor; $held=(string)$wallet->held_minor;
   if($this->cmp($held,$amount)<0) throw new RuntimeException('Wallet hold is inconsistent.');
   $wallet->held_minor=$this->sub($held,$amount); if(!$success)$wallet->available_minor=$this->add($available,$amount); $wallet->save();
   $this->movement($wallet,$key,$tx,$success?'settle_success':'settle_failure',$amount,$available,(string)$wallet->available_minor,$held,(string)$wallet->held_minor);
  });
 }
 public function release(EducationTransaction $tx): void { $this->settle($tx,false); }
 private function wallet(EducationTransaction $tx): WalletAccount {
  $wallet=WalletAccount::query()->where('id',$tx->wallet_account_id)->where('user_id',$tx->user_id)->where('currency',$tx->currency)->lockForUpdate()->first();
  if(!$wallet) throw new RuntimeException('Transaction wallet is unavailable or does not belong to the transaction user.');
  if($wallet->status!=='active') throw new RuntimeException('Transaction wallet is not active.');
  return $wallet;
 }
 private function movement(WalletAccount $w,string $key,EducationTransaction $tx,string $type,string $amount,string $ab,string $aa,string $hb,string $ha):void {
  WalletMovement::create(['wallet_account_id'=>$w->id,'operation_key'=>$key,'reference'=>$tx->client_reference,'type'=>$type,'amount_minor'=>$amount,'currency'=>$w->currency,'available_before_minor'=>$ab,'available_after_minor'=>$aa,'held_before_minor'=>$hb,'held_after_minor'=>$ha,'metadata'=>['education_transaction_id'=>$tx->id]]);
 }
 private function add(string $a,string $b):string { if(function_exists('bcadd'))return bcadd($a,$b,0); return (string)((int)$a+(int)$b); }
 private function sub(string $a,string $b):string { if(function_exists('bcsub'))return bcsub($a,$b,0); return (string)((int)$a-(int)$b); }
 private function cmp(string $a,string $b):int { $a=ltrim($a,'0')?:'0';$b=ltrim($b,'0')?:'0';return strlen($a)<=>strlen($b) ?: strcmp($a,$b); }
}