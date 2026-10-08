<?php
namespace Addons\BankingFinancialIntegrations\Services;

use Addons\BankingFinancialIntegrations\Models\BankDirectory;
use Addons\BankingFinancialIntegrations\Models\BankingTransfer;
use Illuminate\Support\Str;
use RuntimeException;

class BankTransferService
{
 public function create(int $userId, int $bankId, string $accountNumber, float $amount, string $currency='NGN', ?string $idempotencyKey=null): BankingTransfer
 {
  if ($amount <= 0) throw new RuntimeException('Transfer amount must be greater than zero.');
  $bank = BankDirectory::query()->whereKey($bankId)->where('active', true)->first();
  if (!$bank) throw new RuntimeException('Selected bank is not available.');
  $key = $idempotencyKey ?: 'BT-'.Str::uuid();
  $existing = BankingTransfer::query()->where('idempotency_key',$key)->first();
  if ($existing) return $existing;
  $accountNumber = preg_replace('/\D+/', '', $accountNumber) ?? '';
  if (strlen($accountNumber) < 6 || strlen($accountNumber) > 20) throw new RuntimeException('Invalid bank account number.');
  return BankingTransfer::create([
   'user_id'=>$userId,
   'bank_directory_id'=>$bank->id,
   'idempotency_key'=>$key,
   'transfer_reference'=>'TRF-'.strtoupper(Str::random(24)),
   'account_number'=>$accountNumber,
   'amount'=>$amount,
   'currency'=>strtoupper($currency),
   'status'=>'pending',
  ]);
 }

 public function markProcessing(BankingTransfer $transfer, ?string $provider=null): BankingTransfer
 {
  $transfer->forceFill(['status'=>'processing','provider'=>$provider,'processing_started_at'=>now()])->save();
  return $transfer->refresh();
 }

 public function markCompleted(BankingTransfer $transfer, string $providerReference): BankingTransfer
 {
  $transfer->forceFill(['status'=>'completed','provider_reference'=>$providerReference,'completed_at'=>now(),'failure_code'=>null,'failure_reason'=>null])->save();
  return $transfer->refresh();
 }

 public function markFailed(BankingTransfer $transfer, ?string $code=null, ?string $reason=null): BankingTransfer
 {
  $transfer->forceFill(['status'=>'failed','failure_code'=>$code,'failure_reason'=>$reason])->save();
  return $transfer->refresh();
 }

 public function markReversed(BankingTransfer $transfer, ?string $reason=null): BankingTransfer
 {
  $transfer->forceFill(['status'=>'reversed','failure_reason'=>$reason,'reversed_at'=>now()])->save();
  return $transfer->refresh();
 }
}