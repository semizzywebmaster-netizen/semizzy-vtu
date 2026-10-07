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
 public function quote(int $userId,string $serviceKey,?string $productKey,int $baseAmountMinor): int {
  $adapter=$this->adapter($serviceKey,$productKey);
  return $adapter ? (int)$adapter->quote($userId,$serviceKey,$productKey,$baseAmountMinor)['amount_minor'] : $baseAmountMinor;
 }
 public function authorize(int $userId,string $serviceKey,?string $productKey,int $amountMinor): void {
  $this->adapter($serviceKey,$productKey)?->authorize($userId,$serviceKey,$productKey,$amountMinor);
 }
 public function record(int $userId,string $serviceKey,?string $productKey,int $amountMinor,string $transactionKey): void {
  $this->adapter($serviceKey,$productKey)?->record($userId,$serviceKey,$productKey,$amountMinor,$transactionKey);
 }
}
