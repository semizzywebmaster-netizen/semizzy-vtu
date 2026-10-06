<?php

namespace App\Services\Cac;

use App\Models\CacOrder;
use App\Models\CacProviderRoute;
use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;

class CacProviderGateway
{
    public function __construct(private ProviderManager $providers, private CacProviderRouter $router) {}

    public function submit(CacOrder $order): ProviderResult
    {
        $product = $order->product;
        if (!$product) return new ProviderResult(false, 'FAILED', message: 'CAC service product is missing.');
        $routes = $this->router->routesFor($product);
        if (!$routes) return new ProviderResult(false, 'FAILED', message: 'No verified CAC provider route is available.');

        $last = new ProviderResult(false, 'FAILED', message: 'All eligible CAC providers failed safely.');
        foreach ($routes as $route) {
            $payload = $this->payload($order, $route);
            $result = $this->providers->executeProvider(
                $route->provider,
                $product->identifier,
                'transaction_initiation',
                $payload,
                $order->idempotency_key
            );
            $last = $result;
            if ($result->accepted || $result->duplicateRisk || in_array(strtoupper($result->status), ['PENDING','PROCESSING','UNKNOWN'], true)) return $result;
        }
        return $last;
    }

    public function requery(CacOrder $order): ProviderResult
    {
        $provider = $order->api_provider_id ? $order->provider : null;
        if (!$provider || !$order->provider_reference) {
            return new ProviderResult(false, 'UNKNOWN', message: 'Original CAC provider/reference is unavailable.');
        }
        return $this->providers->executeProvider(
            $provider,
            $order->product?->identifier ?? $order->service_type,
            'transaction_status',
            ['reference'=>$order->provider_reference,'transaction_reference'=>$order->reference],
            $order->idempotency_key.':requery'
        );
    }

    private function payload(CacOrder $order, CacProviderRoute $route): array
    {
        return [
            'reference'=>$order->reference,
            'service_type'=>$order->service_type,
            'customer_name'=>$order->customer_name,
            'business_name'=>$order->business_name,
            'company_type'=>$order->company_type,
            'currency'=>$order->currency,
            'amount_minor'=>$order->total_minor,
            'payload'=>$order->request_payload ?? [],
            'endpoint_map'=>$route->endpoint_map ?? [],
        ];
    }
}
