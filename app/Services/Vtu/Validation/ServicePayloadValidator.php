<?php

namespace App\Services\Vtu\Validation;

use App\Models\Service;

interface ServicePayloadValidator
{
    public function validate(Service $service, array $payload): array;
}
