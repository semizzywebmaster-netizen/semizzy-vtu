<?php

namespace App\Services\Vtu\Validation;

use App\Models\Service;
use Illuminate\Validation\ValidationException;

class UtilityPayloadValidator implements ServicePayloadValidator
{
    public function validate(Service $service, array $payload): array
    {
        $rules = match ($service->key) {
            'electricity' => [
                'disco' => ['required', 'string', 'max:100'],
                'meter_number' => ['required', 'string', 'max:100'],
                'meter_type' => ['required', 'string', 'max:30'],
                'amount' => ['required', 'numeric', 'min:1'],
            ],
            'cable_tv' => [
                'provider' => ['required', 'string', 'max:100'],
                'customer_number' => ['required', 'string', 'max:100'],
                'package' => ['required', 'string', 'max:100'],
            ],
            'broadband' => [
                'provider' => ['required', 'string', 'max:100'],
                'account_id' => ['required', 'string', 'max:100'],
                'package' => ['required', 'string', 'max:100'],
            ],
            default => [],
        };

        $validator = validator($payload, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }
}
