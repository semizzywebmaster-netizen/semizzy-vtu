<?php

namespace Tests\Unit;

use App\Models\FinancialOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class FinancialOperationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_and_currency_are_normalized_and_validated(): void
    {
        $operation = FinancialOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'finance-integrity-1',
            'type' => 'test',
            'status' => 'pending',
            'amount_minor' => '0001500',
            'currency' => 'ngn',
        ]);

        $this->assertSame('1500', $operation->amount_minor);
        $this->assertSame('NGN', $operation->currency);
    }

    public function test_terminal_operations_are_immutable(): void
    {
        $operation = FinancialOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'finance-integrity-2',
            'type' => 'test',
            'status' => 'completed',
            'amount_minor' => '100',
            'currency' => 'NGN',
        ]);

        $operation->status = 'failed';

        $this->expectException(LogicException::class);
        $operation->save();
    }

    public function test_financial_operations_cannot_be_deleted(): void
    {
        $operation = FinancialOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'finance-integrity-3',
            'type' => 'test',
            'status' => 'pending',
            'amount_minor' => '100',
            'currency' => 'NGN',
        ]);

        $this->expectException(LogicException::class);
        $operation->delete();
    }
}
