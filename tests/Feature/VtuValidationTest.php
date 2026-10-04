<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Vtu\VtuPayloadValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VtuValidationTest extends TestCase
{
    public function test_airtime_validation_rejects_invalid_phone(): void
    {
        $service = new Service(['key' => 'airtime']);
        $this->expectException(ValidationException::class);

        app(VtuPayloadValidator::class)->validate($service, [
            'network' => 'MTN',
            'phone' => '123',
            'amount' => 100,
        ]);
    }

    public function test_data_validation_requires_plan_and_phone(): void
    {
        $service = new Service(['key' => 'data']);
        $this->expectException(ValidationException::class);

        app(VtuPayloadValidator::class)->validate($service, [
            'network' => 'MTN',
        ]);
    }
}
