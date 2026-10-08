<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Models\EducationProduct;
use Semizzy\Addons\Education\Models\EducationTransaction;
final class EducationProviderRouter {
 public function candidates(EducationProduct $product): array {
  if (!$product->provider_service_code) return [];
  // Provider resolution is delegated to Core ProviderRoutingService; this addon never stores credentials.
  return app(\App\Services\ProviderRoutingService::class)->candidates('education', $product->provider_service_code);
 }
 public function assertCapability(mixed $provider, string $capability): void {
  if (is_object($provider) && method_exists($provider,'supports') && !$provider->supports($capability)) throw new \RuntimeException('Selected education provider does not support '.$capability.'.');
 }
}