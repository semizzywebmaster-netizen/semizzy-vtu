<?php
namespace App\Services\Commercial;
use App\Contracts\CommercialServiceAdapter;
class CommercialServiceRegistry
{
 private array $adapters=[];
 public function register(CommercialServiceAdapter $adapter): void { $this->adapters[$adapter->serviceKey()]=$adapter; }
 public function adapter(string $serviceKey, ?string $productKey=null): ?CommercialServiceAdapter {
  foreach($this->adapters as $adapter) if($adapter->canHandle($serviceKey,$productKey)) return $adapter;
  return null;
 }
 public function quoteDetails(int $userId,string $serviceKey,?string $productKey,int $baseAmountMinor): array {
  $adapter=$this->adapter($serviceKey,$productKey);
  return $adapter ? $adapter->quote($userId,$serviceKey,$productKey,$baseAmountMinor) : ['amount_minor'=>$baseAmountMinor,'partner_id'=>null,'rule_id'=>null];
 }
 public function quote(int $userId,string $serviceKey,?string $productKey,int $baseAmountMinor): int {
  return (int)$this->quoteDetails($userId,$serviceKey,$productKey,$baseAmountMinor)['amount_minor'];
 }
 public function authorize(int $userId,string $serviceKey,?string $productKey,int $amountMinor): void {
  $this->adapter($serviceKey,$productKey)?->authorize($userId,$serviceKey,$productKey,$amountMinor);
 }
 public function record(int $userId,string $serviceKey,?string $productKey,int $amountMinor,string $transactionKey): void {
  $this->adapter($serviceKey,$productKey)?->record($userId,$serviceKey,$productKey,$amountMinor,$transactionKey);
 }
}
