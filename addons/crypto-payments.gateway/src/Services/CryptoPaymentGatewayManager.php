<?php

namespace Semizzy\Addons\CryptoPayments\Services;

use Illuminate\Support\Facades\Log;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;

class CryptoPaymentGatewayManager
{
    public function providersFor(string $capability, ?string $asset = null, ?string $network = null)
    {
        return CryptoPaymentProvider::query()
            ->where('enabled', true)
            ->where('paused', false)
            ->where('maintenance', false)
            ->where(function ($query) {
                $query->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<=', now());
            })
            ->get()
            ->filter(function (CryptoPaymentProvider $provider) use ($capability, $asset, $network) {
                if (! $provider->supports($capability)) {
                    return false;
                }

                if ($asset !== null && ! in_array($asset, $provider->supported_assets ?? [], true)) {
                    return false;
                }

                if ($network !== null && ! in_array($network, $provider->supported_networks ?? [], true)) {
                    return false;
                }

                return true;
            })
            ->sortBy([
                ['priority', 'asc'],
                ['weight', 'desc'],
            ])
            ->values();
    }

    public function execute(string $capability, callable $operation, ?string $asset = null, ?string $network = null): mixed
    {
        $providers = $this->providersFor($capability, $asset, $network);

        if ($providers->isEmpty()) {
            throw new \RuntimeException("No available crypto provider supports {$capability}.");
        }

        $last = null;

        foreach ($providers as $provider) {
            try {
                $result = $operation($provider);
                $provider->forceFill([
                    'failure_count' => 0,
                    'last_success_at' => now(),
                    'last_error' => null,
                ])->save();

                return $result;
            } catch (\Throwable $e) {
                $last = $e;

                $provider->forceFill([
                    'failure_count' => $provider->failure_count + 1,
                    'last_failure_at' => now(),
                    'last_error' => mb_substr($e->getMessage(), 0, 2000),
                    'cooldown_until' => now()->addSeconds(min(300, 15 * max(1, $provider->failure_count + 1))),
                ])->save();

                Log::warning('Crypto payment provider failed.', [
                    'provider_id' => $provider->id,
                    'provider' => $provider->code,
                    'capability' => $capability,
                    'asset' => $asset,
                    'network' => $network,
                    'exception' => $e,
                ]);
            }
        }

        throw $last ?? new \RuntimeException('Crypto provider execution failed.');
    }
}