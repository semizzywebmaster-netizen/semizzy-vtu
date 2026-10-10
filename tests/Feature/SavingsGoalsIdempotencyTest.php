<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\QueryException;
use RuntimeException;
use Semizzy\Addons\Savings\Http\Controllers\SavingsController;
use Semizzy\Addons\Savings\Models\SavingsAccount;
use Semizzy\Addons\Savings\Models\SavingsMovement;
use Semizzy\Addons\Savings\Models\SavingsPlan;
use Tests\TestCase;

class SavingsGoalsIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'addons/savings.goals/database/migrations/2026_10_06_000400_create_savings_plans.php',
            'addons/savings.goals/database/migrations/2026_10_06_000401_create_savings_accounts.php',
            'addons/savings.goals/database/migrations/2026_10_06_000402_create_savings_movements.php',
        ] as $migration) {
            Artisan::call('migrate', ['--path' => $migration, '--force' => true]);
        }
    }

    public function test_savings_goals_contribution_replay_requires_identical_amount_and_identity(): void
    {
        [$user, $account] = $this->fixture();
        SavingsMovement::create([
            'savings_account_id' => $account->id,
            'user_id' => $user->id,
            'operation_key' => 'savings:contribution:'.$account->reference.':same-key',
            'type' => 'contribution',
            'amount_minor' => 1000,
            'currency' => 'NGN',
            'balance_after_minor' => 1000,
            'status' => 'completed',
            'reference' => 'SVM-CONTRIBUTION-1',
        ]);

        $response = app(SavingsController::class)->contribute($this->request($user, 1000, 'same-key'), $account->reference);
        $this->assertTrue($response->getData(true)['idempotent']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This idempotency key has already been used for a different savings operation.');
        app(SavingsController::class)->contribute($this->request($user, 2000, 'same-key'), $account->reference);
    }

    public function test_savings_goals_withdrawal_replay_rejects_changed_amount(): void
    {
        [$user, $account] = $this->fixture(5000);
        SavingsMovement::create([
            'savings_account_id' => $account->id,
            'user_id' => $user->id,
            'operation_key' => 'savings:withdrawal:'.$account->reference.':withdraw-key',
            'type' => 'withdrawal',
            'amount_minor' => 1000,
            'currency' => 'NGN',
            'balance_after_minor' => 4000,
            'status' => 'completed',
            'reference' => 'SVM-WITHDRAWAL-1',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This idempotency key has already been used for a different savings operation.');
        app(SavingsController::class)->withdraw($this->request($user, 2000, 'withdraw-key'), $account->reference);
    }

    public function test_savings_goals_same_withdrawal_replay_succeeds_after_balance_was_reduced(): void
    {
        [$user, $account] = $this->fixture(0);
        SavingsMovement::create([
            'savings_account_id' => $account->id,
            'user_id' => $user->id,
            'operation_key' => 'savings:withdrawal:'.$account->reference.':completed-key',
            'type' => 'withdrawal',
            'amount_minor' => 1000,
            'currency' => 'NGN',
            'balance_after_minor' => 0,
            'status' => 'completed',
            'reference' => 'SVM-WITHDRAWAL-REPLAY-1',
        ]);

        $response = app(SavingsController::class)->withdraw($this->request($user, 1000, 'completed-key'), $account->reference);

        $this->assertTrue($response->getData(true)['idempotent']);
        $this->assertSame('SVM-WITHDRAWAL-REPLAY-1', $response->getData(true)['movement']['reference']);
    }

    public function test_savings_movement_operation_key_is_unique_at_database_level(): void
    {
        [$user, $account] = $this->fixture();

        $attributes = [
            'savings_account_id' => $account->id,
            'user_id' => $user->id,
            'operation_key' => 'savings:contribution:'.$account->reference.':unique-key',
            'type' => 'contribution',
            'amount_minor' => 1000,
            'currency' => 'NGN',
            'balance_after_minor' => 1000,
            'status' => 'completed',
            'reference' => 'SVM-UNIQUE-1',
        ];
        SavingsMovement::create($attributes);

        $this->expectException(QueryException::class);
        SavingsMovement::create(array_merge($attributes, ['reference' => 'SVM-UNIQUE-2']));
    }

    private function fixture(int $balance = 0): array
    {
        $user = User::factory()->create(['role' => 'USER', 'status' => 'active']);
        $plan = SavingsPlan::create([
            'key' => 'idempotency-test-plan',
            'name' => 'Idempotency Test Plan',
            'type' => 'flexible',
            'minimum_amount_minor' => 100,
            'maximum_amount_minor' => 100000,
            'lock_days' => 0,
            'allow_early_withdrawal' => true,
            'active' => true,
        ]);
        $account = SavingsAccount::create([
            'user_id' => $user->id,
            'savings_plan_id' => $plan->id,
            'reference' => 'SAV-IDEMPOTENCY-'.strtoupper(bin2hex(random_bytes(4))),
            'name' => 'Test savings',
            'currency' => 'NGN',
            'balance_minor' => (string) $balance,
            'status' => 'active',
        ]);

        return [$user, $account];
    }

    private function request(User $user, int $amount, string $key): Request
    {
        $request = Request::create('/test/savings', 'POST', ['amount_minor' => $amount], [], [], [
            'HTTP_IDEMPOTENCY_KEY' => $key,
        ]);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
