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
  $limits = (array) config('banking.financial_integrations', []);
  $bank = BankDirectory::query()->whereKey($bankId)->where('active', true)->first();
  if (!$bank) throw new RuntimeException('Selected bank is not available.');
  $key = $idempotencyKey ?: 'BT-'.Str::uuid();
  $existing = BankingTransfer::query()->where('idempotency_key',$key)->first();
  if ($existing) return $existing;
  $accountNumber = preg_replace('/\D+/', '', $accountNumber) ?? '';
  if (strlen($accountNumber) < 6 || strlen($accountNumber) > 20) throw new RuntimeException('Invalid bank account number.');
  $min = (float) (($limits['bank_transfer_min_amount_minor'] ?? 0) / 100);
  $max = (float) (($limits['bank_transfer_max_amount_minor'] ?? PHP_INT_MAX) / 100);
  if ($amount < $min) throw new RuntimeException('Transfer amount is below the configured minimum.');
  if ($amount > $max) throw new RuntimeException('Transfer amount exceeds the configured maximum.');
  $components = $limits['bank_transfer_fee_components'] ?? [];
  $transferFee = (float) (($components['transfer_fee_minor'] ?? $limits['bank_transfer_fee_minor'] ?? 0) / 100);
  $vatFee = (float) (($components['vat_minor'] ?? 0) / 100);
  $otherFee = (float) (($components['other_ng_fee_minor'] ?? 0) / 100);
  $totalFee = $transferFee + $vatFee + $otherFee;
  return BankingTransfer::create([
   'user_id'=>$userId,
   'bank_directory_id'=>$bank->id,
   'idempotency_key'=>$key,
   'transfer_reference'=>'TRF-'.strtoupper(Str::random(24)),
   'account_number'=>$accountNumber,
   'amount'=>$amount,
   'currency'=>strtoupper($currency),
   'status'=>'pending',
   'fee'=>$totalFee,
   'transfer_fee'=>$transferFee,
   'vat_fee'=>$vatFee,
   'other_ng_fee'=>$otherFee,
   'total_fee'=>$totalFee,
   'total_debit'=>$amount + $totalFee,
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