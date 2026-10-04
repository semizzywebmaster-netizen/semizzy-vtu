<?php

namespace App\Services\Providers;

final readonly class ProviderResult
{
    public function __construct(
        public bool $accepted,
        public string $status,
        public ?string $providerReference = null,
        public mixed $data = null,
        public ?string $message = null,
        public bool $retryable = false,
        public bool $duplicateRisk = false,
        public ?int $providerId = null,
    ) {}
}
