<?php

namespace App\Services\Vtu\Validation;

use App\Models\Service;
use Illuminate\Validation\ValidationException;

class AirtimePayloadValidator implements ServicePayloadValidator
{
    public function validate(Service $service, array $payload): array
    {
        $validator = validator($payload, [
            'network' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'regex:/^(?:\+?234|0)\d{10}$/'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        return $validator->validated();
    }
}
