<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;

interface ProviderAdapter
{
    public function supports(string $operation): bool;
    public function execute(ApiProvider $provider, string $operation, array $payload = []): ProviderResult;
}
