<?php

namespace App\Services\Vtu;

use App\Models\Service;
use App\Services\Vtu\Validation\VtuPayloadValidationRegistry;

class VtuPayloadValidator
{
    public function __construct(private VtuPayloadValidationRegistry $registry) {}

    public function validate(Service $service, array $payload): array
    {
        return $this->registry->validator($service)->validate($service, $payload);
    }
}
