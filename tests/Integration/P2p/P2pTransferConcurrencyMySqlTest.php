<?php

namespace Tests\Integration\P2p;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Semizzy\Addons\P2p\Services\P2pTransferService;
use Tests\TestCase;

class P2pTransferConcurrencyMySqlTest extends TestCase
{
    private bool $createdP2pTable = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('p2p_transfers')) {
            Schema::create('p2p_transfers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
                $table->string('reference', 64)->unique();
                $table->string('idempotency_key', 120);
                $table->unsignedBigInteger('amount_minor');
                $table->unsignedBigInteger('fee_minor')->default(0);
                $table->unsignedBigInteger('total_debit_minor')->default(0);
                $table->string('currency', 3);
                $table->string('status', 24)->default('completed');
                $table->string('note', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['sender_id', 'idempotency_key']);
            });
            $this->createdP2pTable = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdP2pTable && Schema::hasTable('p2p_transfers')) {
            Schema::drop('p2p_transfers');
        }
        parent::tearDown();
    }

    public function test_concurrent_same_key_transfers_only_move_funds_once(): void
    {
        $this->requireMySqlAndPcntl();
        [$sender, $recipient] = $this->usersWithWallets('100000', '5000');

        $results = $this->runConcurrently([
            fn () => $this->transferResult($sender->id, $recipient->id, '25000', 'p2p-same-key-race'),
            fn () => $this->transferResult($sender->id, $recipient->id, '25000', 'p2p-same-key-race'),
        ]);

        sort($results);
        $this->assertSame(['success', 'success'], $results);
        $this->assertSame('75000', (string) WalletAccount::query()->where('user_id', $sender->id)->value('available_minor'));
        $this->assertSame('30000', (string) WalletAccount::query()->where('user_id', $recipient->id)->value('available_minor'));
        $this->assertSame(1, DB::table('p2p_transfers')->where('sender_id', $sender->id)->count());
        $this->assertSame(2, WalletMovement::query()->whereIn('wallet_account_id', WalletAccount::query()->whereIn('user_id', [$sender->id, $recipient->id])->pluck('id'))->count());
    }

    public function test_concurrent_transfers_cannot_both_spend_the_same_sender_balance(): void
    {
        $this->requireMySqlAndPcntl();
        $sender = User::factory()->create();
        $recipientA = User::factory()->create();
        $recipientB = User::factory()->create();
        $this->wallet($sender, '100000');
        $this->wallet($recipientA, '5000');
        $this->wallet($recipientB, '7000');

        $results = $this->runConcurrently([
            fn () => $this->transferResult($sender->id, $recipientA->id, '70000', 'p2p-funds-race-a'),
            fn () => $this->transferResult($sender->id, $recipientB->id, '70000', 'p2p-funds-race-b'),
        ]);

        sort($results);
        $this->assertSame(['insufficient', 'success'], $results);
        $this->assertSame('30000', (string) WalletAccount::query()->where('user_id', $sender->id)->value('available_minor'));
        $this->assertSame(1, DB::table('p2p_transfers')->where('sender_id', $sender->id)->count());
        $this->assertSame(2, WalletMovement::query()->whereIn('wallet_account_id', WalletAccount::query()->whereIn('user_id', [$sender->id, $recipientA->id, $recipientB->id])->pluck('id'))->count());
        $recipientBalances = WalletAccount::query()->whereIn('user_id', [$recipientA->id, $recipientB->id])->pluck('available_minor')->map(fn ($value) => (string) $value)->sort()->values()->all();
        $this->assertTrue(
            in_array($recipientBalances, [['5000', '77000'], ['7000', '75000']], true),
            'Exactly one recipient must receive the transfer, regardless of which concurrent worker wins.'
        );
    }

    private function transferResult(int $senderId, int $recipientId, string $amountMinor, string $key): string
    {
        try {
            $recipient = User::query()->findOrFail($recipientId);
            app(P2pTransferService::class)->transfer($senderId, (string) $recipient->username, $amountMinor, 'MySQL concurrency regression', $key);
            return 'success';
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Insufficient wallet balance')) {
                return 'insufficient';
            }
            throw $exception;
        }
    }

    private function usersWithWallets(string $senderBalance, string $recipientBalance): array
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $this->wallet($sender, $senderBalance);
        $this->wallet($recipient, $recipientBalance);

        return [$sender, $recipient];
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
     * @param array<int, callable(): string> $workers
     * @return array<int, string>
     */
    private function runConcurrently(array $workers): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'semizzy-p2p-race-');
        if ($barrier === false) {
            $this->fail('Could not create the concurrency barrier.');
        }
        unlink($barrier);

        $children = [];
        $resultFiles = [];

        foreach ($workers as $worker) {
            $resultFile = tempnam(sys_get_temp_dir(), 'semizzy-p2p-result-');
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
