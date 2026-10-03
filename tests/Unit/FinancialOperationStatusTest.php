<?php

namespace Tests\Unit;

use App\Models\FinancialOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class FinancialOperationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_statuses_are_accepted(): void
    {
        foreach (['pending', 'processing', 'completed', 'failed', 'cancelled', 'reversed'] as $status) {
            $operation = FinancialOperation::create([
                'uuid' => (string) Str::uuid(),
                'reference' => 'status-'.$status.'-'.Str::random(8),
                'type' => 'test',
                'status' => strtoupper($status),
                'amount_minor' => '100',
                'currency' => 'ngn',
            ]);

            $this->assertSame($status, $operation->status);
        }
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FinancialOperation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'status-invalid-'.Str::random(8),
            'type' => 'test',
            'status' => 'bogus',
            'amount_minor' => '100',
            'currency' => 'NGN',
        ]);
    }
}
