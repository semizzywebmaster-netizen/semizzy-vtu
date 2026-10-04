<?php

namespace App\Services\Vtu\Validation;

use App\Models\Service;
use Illuminate\Validation\ValidationException;

class MetadataPayloadValidator implements ServicePayloadValidator
{
    public function validate(Service $service, array $payload): array
    {
        $rules = [];
        foreach ((array) ($service->metadata['required_fields'] ?? []) as $field) {
            $rules[$field] = ['required'];
        }

        $validator = validator($payload, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }
}
