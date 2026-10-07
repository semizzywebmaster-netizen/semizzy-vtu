<?php
namespace App\Contracts;
interface CommercialServiceAdapter
{
 public function serviceKey(): string;
 public function canHandle(string $serviceKey, ?string $productKey = null): bool;
 public function quote(int $userId, string $serviceKey, ?string $productKey, int $baseAmountMinor): array;
 public function authorize(int $userId, string $serviceKey, ?string $productKey, int $amountMinor): void;
 public function record(int $userId, string $serviceKey, ?string $productKey, int $amountMinor, string $transactionKey): void;
}
