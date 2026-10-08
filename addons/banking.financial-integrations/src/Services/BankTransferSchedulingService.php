<?php
namespace Addons\BankingFinancialIntegrations\Services;
use Addons\BankingFinancialIntegrations\Models\ScheduledBankTransfer;
use Addons\BankingFinancialIntegrations\Models\BankTransferBatch;
use Illuminate\Support\Str;
use RuntimeException;
class BankTransferSchedulingService {
 public function schedule(int $userId, string $channel, string $mode, \DateTimeInterface $when, string $currency='NGN', float $totalAmount=0, float $totalFee=0, ?string $idempotencyKey=null): ScheduledBankTransfer {
  if (!in_array($channel,['bank','p2p'],true)) throw new RuntimeException('Unsupported transfer channel.');
  if (!in_array($mode,['single','bulk'],true)) throw new RuntimeException('Unsupported transfer mode.');
  if ($when <= new \DateTimeImmutable()) throw new RuntimeException('Scheduled transfer must be in the future.');
  $key=$idempotencyKey ?: 'SCH-'.Str::uuid();
  $existing=ScheduledBankTransfer::query()->where('idempotency_key',$key)->first();
  if($existing) return $existing;
  return ScheduledBankTransfer::create(['user_id'=>$userId,'channel'=>$channel,'mode'=>$mode,'scheduled_for'=>$when,'currency'=>strtoupper($currency),'total_amount'=>$totalAmount,'total_fee'=>$totalFee,'batch_reference'=>'BATCH-'.strtoupper(Str::random(24)),'idempotency_key'=>$key]);
 }
 public function cancel(ScheduledBankTransfer $schedule): ScheduledBankTransfer {
  if(in_array($schedule->status,['completed','cancelled'],true)) return $schedule;
  $schedule->forceFill(['status'=>'cancelled','cancelled_at'=>now()])->save();
  return $schedule->refresh();
 }
 public function due(int $limit=100) { return ScheduledBankTransfer::query()->where('status','scheduled')->where('scheduled_for','<=',now())->orderBy('scheduled_for')->limit($limit)->get(); }
 public function createBulkBatch(int $userId,string $channel,int $totalItems,float $totalAmount,float $totalFee=0,?string $idempotencyKey=null): BankTransferBatch {
  if(!in_array($channel,['bank','p2p'],true)) throw new RuntimeException('Unsupported transfer channel.');
  if($totalItems<1) throw new RuntimeException('Bulk transfer must contain at least one item.');
  $key=$idempotencyKey ?: 'BULK-'.Str::uuid();
  $existing=BankTransferBatch::query()->where('idempotency_key',$key)->first();
  if($existing) return $existing;
  return BankTransferBatch::create(['user_id'=>$userId,'channel'=>$channel,'batch_reference'=>'BATCH-'.strtoupper(Str::random(24)),'total_items'=>$totalItems,'total_amount'=>$totalAmount,'total_fee'=>$totalFee,'idempotency_key'=>$key]);
 }
}