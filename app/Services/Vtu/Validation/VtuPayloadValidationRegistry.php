<?php

namespace App\Services\Vtu\Validation;

use App\Models\Service;

class VtuPayloadValidationRegistry
{
    public function __construct(
        private AirtimePayloadValidator $airtime,
        private DataPayloadValidator $data,
        private UtilityPayloadValidator $utility,
        private MetadataPayloadValidator $metadata,
    ) {}

    public function validator(Service $service): ServicePayloadValidator
    {
        return match ($service->key) {
            'airtime' => $this->airtime,
            'data' => $this->data,
            'electricity', 'cable_tv', 'broadband' => $this->utility,
            default => $this->metadata,
        };
    }
}
