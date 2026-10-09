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

    public function test_pending_operations_cannot_mutate_immutable_identity_fields(): void
    {
        $operation = FinancialOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reference' => 'finance-integrity-immutable',
            'type' => 'funding',
            'status' => 'pending',
            'amount_minor' => '1000',
            'currency' => 'NGN',
            'idempotency_key' => 'idem-immutable',
        ]);

        $operation->amount_minor = '2000';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Financial operation amount_minor is immutable after creation.');
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
    public function test_completed_financial_operation_can_be_reversed_once_but_not_reopened(): void
    {
        $operation = FinancialOperation::create([
            'uuid' => (string) \\Illuminate\\Support\\Str::uuid(),
            'reference' => 'finance-reversal-'.\\Illuminate\\Support\\Str::random(8),
            'type' => 'transfer',
            'status' => 'completed',
            'amount_minor' => '500',
            'currency' => 'NGN',
        ]);

        $operation->status = 'reversed';
        $operation->save();
        $this->assertSame('reversed', $operation->fresh()->status);

        $operation->status = 'completed';
        $this->expectException(LogicException::class);
        $operation->save();
    }

}
