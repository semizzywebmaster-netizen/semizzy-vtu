<?php
namespace Addons\BankingFinancialIntegrations\Services;

use Addons\BankingFinancialIntegrations\Models\BankingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BankingProviderGateway
{
 public function providersFor(string $capability)
 {
  return BankingProvider::query()
   ->where('enabled', true)->where('paused', false)->where('maintenance', false)
   ->where(fn($q) => $q->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', now()))
   ->whereJsonContains('capabilities', $capability)
   ->orderBy('priority')->orderByDesc('weight')->get();
 }

 public function healthCheck(BankingProvider $provider): bool
 {
  $credentials = $provider->credentials ?: [];
  $url = $credentials['health_url'] ?? $provider->base_url;
  if (!$url) throw new RuntimeException('Provider health endpoint is not configured.');

  try {
   $response = Http::timeout((int)($credentials['timeout'] ?? 15))->get($url);
   $provider->update([
    'last_health_check_at' => now(),
    'failure_count' => $response->successful() ? 0 : $provider->failure_count + 1,
    'last_success_at' => $response->successful() ? now() : $provider->last_success_at,
    'last_failure_at' => $response->successful() ? $provider->last_failure_at : now(),
    'cooldown_until' => $response->successful() ? null : now()->addMinutes(5),
   ]);
   return $response->successful();
  } catch (\Throwable $e) {
   $provider->update([
    'last_health_check_at' => now(),
    'failure_count' => $provider->failure_count + 1,
    'last_failure_at' => now(),
    'cooldown_until' => now()->addMinutes(5),
   ]);
   return false;
  }
 }
}