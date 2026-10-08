<?php

namespace Semizzy\Addons\CryptoPayments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use Semizzy\Addons\CryptoPayments\Services\CryptoPaymentGatewayAdapterRegistry;
use RuntimeException;

class CryptoPaymentProviderAdminController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CryptoPaymentProvider::query()
                ->orderBy('priority')
                ->orderBy('id')
                ->get()
                ->makeHidden(['credentials']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:crypto_payment_providers,code'],
            'driver' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'credentials' => ['nullable', 'array'],
            'capabilities' => ['nullable', 'array'],
            'supported_assets' => ['nullable', 'array'],
            'supported_networks' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'weight' => ['nullable', 'integer', 'min:1'],
            'enabled' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
        ]);

        $provider = CryptoPaymentProvider::create($data);

        return response()->json(['data' => $provider->makeHidden(['credentials'])], 201);
    }

    public function update(Request $request, CryptoPaymentProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'driver' => ['sometimes', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'credentials' => ['sometimes', 'array'],
            'capabilities' => ['sometimes', 'array'],
            'supported_assets' => ['sometimes', 'array'],
            'supported_networks' => ['sometimes', 'array'],
            'priority' => ['sometimes', 'integer', 'min:1'],
            'weight' => ['sometimes', 'integer', 'min:1'],
            'enabled' => ['sometimes', 'boolean'],
            'paused' => ['sometimes', 'boolean'],
            'maintenance' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
        ]);

        $provider->fill($data)->save();

        return response()->json(['data' => $provider->fresh()->makeHidden(['credentials'])]);
    }

    public function toggle(CryptoPaymentProvider $provider): JsonResponse
    {
        $provider->enabled = !$provider->enabled;
        $provider->save();

        return response()->json(['data' => $provider->fresh()->makeHidden(['credentials'])]);
    }

    public function test(CryptoPaymentProvider $provider, CryptoPaymentGatewayAdapterRegistry $registry): JsonResponse
    {
        if (!$registry->has($provider->driver)) {
            throw new RuntimeException('No adapter is registered for this provider driver.');
        }

        return response()->json([
            'ok' => $registry->make($provider)->healthCheck(),
        ]);
    }
}
