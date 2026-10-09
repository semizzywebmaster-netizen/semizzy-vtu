<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Services\Finance\AdminWalletDebitService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class WalletConcurrencyMySqlTest extends TestCase
{
    public function test_concurrent_same_key_admin_debits_only_apply_one_movement(): void
    {
        $this->requireMySqlAndPcntl();
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $wallet = $this->wallet($user, '100000');

        $results = $this->runConcurrently([
            fn () => $this->debitResult($user->id, $admin->id, '250.00', 'same-key-race'),
            fn () => $this->debitResult($user->id, $admin->id, '250.00', 'same-key-race'),
        ]);

        sort($results);
        $this->assertSame(['success', 'success'], $results);
        $this->assertSame('75000', (string) $wallet->fresh()->available_minor);
        $this->assertSame(1, WalletMovement::query()->where('wallet_account_id', $wallet->id)->count());
    }

    public function test_concurrent_debits_cannot_both_spend_the_same_available_funds(): void
    {
        $this->requireMySqlAndPcntl();
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $wallet = $this->wallet($user, '100000');

        $results = $this->runConcurrently([
            fn () => $this->debitResult($user->id, $admin->id, '700.00', 'insufficient-race-a'),
            fn () => $this->debitResult($user->id, $admin->id, '700.00', 'insufficient-race-b'),
        ]);

        sort($results);
        $this->assertSame(['insufficient', 'success'], $results);
        $this->assertSame('30000', (string) $wallet->fresh()->available_minor);
        $this->assertSame(1, WalletMovement::query()->where('wallet_account_id', $wallet->id)->count());
    }

    private function debitResult(int $userId, int $adminId, string $amount, string $key): string
    {
        try {
            app(AdminWalletDebitService::class)->debit(
                User::query()->findOrFail($userId),
                $amount,
                User::query()->findOrFail($adminId),
                'MySQL concurrency regression',
                $key
            );
            return 'success';
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Insufficient available wallet balance')) {
                return 'insufficient';
            }
            throw $exception;
        }
    }

    private function wallet(User $user, string $availableMinor): WalletAccount
    {
        return WalletAccount::query()->create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => $availableMinor,
            'held_minor' => '0',
            'status' => 'active',
        ]);
    }

    private function requireMySqlAndPcntl(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Real row-lock concurrency tests run only against MySQL.');
        }
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            $this->markTestSkipped('The pcntl extension is required for process-level concurrency tests.');
        }
    }

    /**
     * Fork independent PHP processes and release them together after all workers
     * have connected to the same committed MySQL fixture data.
     *
     * @param array<int, callable(): string> $workers
     * @return array<int, string>
     */
    private function runConcurrently(array $workers): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'semizzy-race-');
        if ($barrier === false) {
            $this->fail('Could not create the concurrency barrier.');
        }
        unlink($barrier);

        $children = [];
        $resultFiles = [];

        foreach ($workers as $worker) {
            $resultFile = tempnam(sys_get_temp_dir(), 'semizzy-result-');
            if ($resultFile === false) {
                $this->fail('Could not create a worker result file.');
            }
            $resultFiles[] = $resultFile;
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Could not fork a concurrency worker.');
            }

            if ($pid === 0) {
                try {
                    DB::purge('mysql');
                    DB::reconnect('mysql');
                    $deadline = microtime(true) + 15;
                    while (!file_exists($barrier) && microtime(true) < $deadline) {
                        usleep(1000);
                    }
                    if (!file_exists($barrier)) {
                        throw new RuntimeException('Timed out waiting for the concurrency barrier.');
                    }
                    file_put_contents($resultFile, 'ok:'.$worker());
                    exit(0);
                } catch (\Throwable $exception) {
                    file_put_contents($resultFile, 'error:'.$exception->getMessage());
                    exit(1);
                }
            }

            $children[] = $pid;
        }

        // All child processes exist before the shared release signal is created.
        file_put_contents($barrier, 'go');
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        foreach ($resultFiles as $resultFile) {
            $result = (string) file_get_contents($resultFile);
            @unlink($resultFile);
            if (!str_starts_with($result, 'ok:')) {
                @unlink($barrier);
                $this->fail('Concurrent worker failed: '.$result);
            }
            $results[] = substr($result, 3);
        }
        @unlink($barrier);

        return $results;
    }
}
