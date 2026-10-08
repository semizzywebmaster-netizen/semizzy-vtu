<?php
namespace Semizzy\Addons\Education\Contracts;
use Semizzy\Addons\Education\Models\EducationTransaction;
interface EducationProviderAdapter {
 public function code(): string;
 public function initiate(EducationTransaction $transaction): EducationProviderResult;
 public function status(EducationTransaction $transaction): EducationProviderResult;
 public function verify(EducationTransaction $transaction): EducationProviderResult;
 public function syncInstitutions(): array;
 public function syncProducts(): array;
}
final class EducationProviderResult {
 public function __construct(
  public readonly string $status,
  public readonly ?string $providerReference=null,
  public readonly array $data=[],
  public readonly ?string $message=null
 ) {}
 public function isSuccessful(): bool { return $this->status==='successful'; }
 public function isFailed(): bool { return $this->status==='failed'; }
 public function isUncertain(): bool { return in_array($this->status,['pending','processing','unknown'],true); }
}