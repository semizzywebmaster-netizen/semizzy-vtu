<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Contracts\EducationProviderAdapter;
use RuntimeException;
final class EducationProviderRegistry {
 private array $adapters=[];
 public function register(EducationProviderAdapter $adapter): void { $this->adapters[$adapter->code()]=$adapter; }
 public function get(string $code): EducationProviderAdapter {
  $adapter=$this->adapters[$code]??null;
  if(!$adapter) throw new RuntimeException('Education provider adapter is not registered for '.$code.'.');
  return $adapter;
 }
 public function has(string $code): bool { return isset($this->adapters[$code]); }
}