<?php
namespace Addons\BankingFinancialIntegrations\Services;

use Addons\BankingFinancialIntegrations\Models\AccountVerification;
use Addons\BankingFinancialIntegrations\Models\BankDirectory;
use Illuminate\Support\Str;
use RuntimeException;

class BankAccountVerificationService
{
 public function createRequest(?int $userId, int $bankId, string $accountNumber): AccountVerification
 {
  $bank = BankDirectory::query()->whereKey($bankId)->where('active', true)->first();
  if (!$bank) throw new RuntimeException('Selected bank is not available.');
  $accountNumber = preg_replace('/\D+/', '', $accountNumber) ?? '';
  if (strlen($accountNumber) < 6 || strlen($accountNumber) > 20) {
   throw new RuntimeException('Invalid bank account number.');
  }
  return AccountVerification::create([
   'user_id'=>$userId,
   'bank_directory_id'=>$bank->id,
   'account_number'=>$accountNumber,
   'request_reference'=>'BAV-'.strtoupper(Str::random(24)),
   'status'=>'pending',
  ]);
 }
}