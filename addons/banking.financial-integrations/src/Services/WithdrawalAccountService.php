<?php
namespace Addons\BankingFinancialIntegrations\Services;

use Addons\BankingFinancialIntegrations\Models\AccountVerification;
use Addons\BankingFinancialIntegrations\Models\WithdrawalAccount;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WithdrawalAccountService
{
 public function add(
  int $userId,
  int $bankId,
  string $accountNumber,
  string $verifiedAccountName,
  string $verifiedKycName,
  array $settings=[]
 ): WithdrawalAccount {
  $max=(int)($settings['withdrawal_account_max'] ?? 2);
  $max=max(1,$max);
  $existing=WithdrawalAccount::query()->where('user_id',$userId)->where('active',true)->count();
  if($existing >= $max) throw new RuntimeException('You have reached the maximum number of withdrawal accounts.');

  $accountNumber=preg_replace('/\D+/','',$accountNumber) ?? '';
  if(strlen($accountNumber)!==10) throw new RuntimeException('Nigerian withdrawal account number must contain 10 digits.');
  $accountName=trim($verifiedAccountName);
  $kycName=trim($verifiedKycName);
  if($accountName==='' || $kycName==='') throw new RuntimeException('A verified bank account name and verified KYC name are required.');
  if(($settings['withdrawal_account_kyc_name_match_required'] ?? true) && !$this->namesMatch($accountName,$kycName)){
   throw new RuntimeException('Withdrawal account name must match the verified KYC name.');
  }

  $verification=AccountVerification::query()
   ->where('user_id',$userId)->where('bank_directory_id',$bankId)
   ->where('account_number',$accountNumber)->where('status','verified')
   ->latest('verified_at')->first();
  if(!$verification) throw new RuntimeException('Bank account must be successfully verified before it can be used for withdrawal.');

  return DB::transaction(function() use($userId,$bankId,$accountNumber,$accountName,$kycName,$verification){
   $duplicate=WithdrawalAccount::query()->where('user_id',$userId)->where('bank_directory_id',$bankId)->where('account_number',$accountNumber)->first();
   if($duplicate) return $duplicate->forceFill(['active'=>true,'account_name'=>$accountName,'kyc_name_matched'=>true,'kyc_name_snapshot'=>$kycName,'verified_at'=>now(),'account_verification_id'=>$verification->id])->save() ? $duplicate->refresh() : $duplicate;
   return WithdrawalAccount::create([
    'user_id'=>$userId,'bank_directory_id'=>$bankId,'account_verification_id'=>$verification->id,
    'account_number'=>$accountNumber,'account_name'=>$accountName,'kyc_name_matched'=>true,
    'kyc_name_snapshot'=>$kycName,'verified_at'=>now(),'active'=>true,
   ]);
  });
 }

 private function namesMatch(string $a,string $b): bool
 {
  $normalize=function(string $v): string {
   $v=strtoupper($v);
   $v=preg_replace('/[^A-Z0-9 ]+/',' ',$v) ?? '';
   $v=preg_replace('/\s+/',' ',$v) ?? '';
   return trim($v);
  };
  return $normalize($a)===$normalize($b);
 }
}
