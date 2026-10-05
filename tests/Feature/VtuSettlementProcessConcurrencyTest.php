<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\FinancialOperation;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Models\VtuTransaction;
use App\Models\WalletAccount;
use App\Services\Audit\AuditLogger;
use App\Services\Providers\ProviderResult;
use App\Services\Vtu\VtuProviderGateway;
use App\Services\Vtu\VtuTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VtuSettlementProcessConcurrencyTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl is required for the genuine concurrent-process regression test.');
        }

        $this->databasePath = storage_path('framework/testing/vtu-concurrency-'.Str::uuid().'.sqlite');
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            @unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_two_real_processes_can_race_the_same_transaction_without_double_settlement(): void
    {
        $user = User::create([
            'name' => 'Concurrent Settlement',
            'email' => 'concurrent-settlement@example.test',
            'password' => 'password',
            'role' => 'USER',
            'status' => 'active',
        ]);

        WalletAccount::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'available_minor' => '0',
            'held_minor' => '10000',
            'status' => 'active',
        ]);

        $category = ServiceCategory::create([
            'key' => 'concurrency',
            'name' => 'Concurrency',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'key' => 'concurrency-service',
            'name' => 'Concurrency Service',
            'enabled' => true,
        ]);

        $product = ServiceProduct::create([
            'service_id' => $service->id,
            'key' => 'concurrency-product',
            'name' => 'Concurrency Product',
            'provider_cost' => '100.00',
            'currency' => 'NGN',
            'enabled' => true,
        ]);

        $operation = FinancialOperation::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'CONCURRENT-1',
            'user_id' => $user->id,
            'type' => 'vtu.purchase',
            'status' => 'processing',
            'amount_minor' => '10000',
            'currency' => 'NGN',
            'idempotency_key' => 'concurrent-1',
            'metadata' => [],
        ]);

        $tx = VtuTransaction::create([
            'uuid' => (string) Str::uuid(),
            'reference' => 'VTU-CONCURRENT-1',
            'user_id' => $user->id,
            'service_id' => $service->id,
            'service_product_id' => $product->id,
            'financial_operation_id' => $operation->id,
            'idempotency_key' => 'concurrent-1',
            'status' => 'processing',
            'amount_minor' => '10000',
            'fee_minor' => '0',
            'total_minor' => '10000',
            'currency' => 'NGN',
            'customer_tier' => 'USER',
            'request_payload' => ['phone' => '08000000000'],
            'metadata' => [],
        ]);

        $operation->metadata = ['vtu_transaction_id' => $tx->id];
        $operation->save();

        $counterPath = storage_path('framework/testing/vtu-provider-count-'.Str::uuid().'.txt');
        file_put_contents($counterPath, '0');

        $gateway = new class($counterPath) extends VtuProviderGateway {
            public function __construct(private string $counterPath) {}

            public function initiate(VtuTransaction $tx, array $payload): ProviderResult
            {
                $handle = fopen($this->counterPath, 'c+');
                if ($handle === false) {
                    throw new \RuntimeException('Unable to open concurrency counter.');
                }

                flock($handle, LOCK_EX);
                $count = (int) trim(stream_get_contents($handle));
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, (string) ($count + 1));
                fflush($handle);
                flock($handle, LOCK_UN);
                fclose($handle);

                usleep(100000);

                return new ProviderResult(
                    accepted: true,
                    status: 'SUCCESSFUL',
                    providerReference: 'PROVIDER-CONCURRENT-1',
                    data: ['ok' => true],
                );
            }
        };

        $this->app->instance(VtuProviderGateway::class, $gateway);
        $this->app->instance(AuditLogger::class, new class extends AuditLogger {
            public function __construct() {}

            public function record(string $event, ?object $subject = null, array $context = [], ?Request $request = null): AuditEvent
            {
                return new AuditEvent();
            }
        });

        $startPath = storage_path('framework/testing/vtu-concurrency-start-'.Str::uuid().'.signal');
        $childResultPath = storage_path('framework/testing/vtu-concurrency-child-'.Str::uuid().'.result');

        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->fail('Unable to fork the concurrent settlement regression process.');
        }

        if ($pid === 0) {
            try {
                DB::disconnect('sqlite');
                DB::reconnect('sqlite');

                file_put_contents($startPath, 'child-ready');
                while (! is_file($startPath.'.go')) {
                    usleep(10000);
                }

                $childTx = VtuTransaction::query()->findOrFail($tx->id);
                app(VtuTransactionService::class)->process($childTx);

                file_put_contents($childResultPath, 'success');
                exit(0);
            } catch (\Throwable $e) {
                file_put_contents($childResultPath, 'failure: '.$e::class.' '.$e->getMessage());
                exit(1);
            }
        }

        $deadline = microtime(true) + 5;
        while (! is_file($startPath) && microtime(true) < $deadline) {
            usleep(10000);
        }

        if (! is_file($startPath)) {
            posix_kill($pid, SIGTERM);
            pcntl_waitpid($pid, $status);
            $this->fail('Concurrent child process did not become ready.');
        }

        file_put_contents($startPath.'.go', 'go');

        $parentTx = VtuTransaction::query()->findOrFail($tx->id);
        app(VtuTransactionService::class)->process($parentTx);

        pcntl_waitpid($pid, $status);

        $wallet = WalletAccount::query()->where('user_id', $user->id)->where('currency', 'NGN')->firstOrFail();
        $operation->refresh();
        $final = $tx->fresh();

        $this->assertTrue(pcntl_wifexited($status), is_file($childResultPath) ? file_get_contents($childResultPath) : 'child result missing');
        $this->assertSame(0, pcntl_wexitstatus($status), is_file($childResultPath) ? file_get_contents($childResultPath) : 'child result missing');
        $this->assertSame('1', trim((string) file_get_contents($counterPath)));
        $this->assertSame('successful', $final->status);
        $this->assertSame('0', (string) $wallet->held_minor);
        $this->assertSame('0', (string) $wallet->available_minor);
        $this->assertSame('completed', $operation->status);
        $this->assertTrue((bool) (($final->metadata ?? [])['financial_settlement_applied'] ?? false));

        @unlink($counterPath);
        @unlink($startPath);
        @unlink($startPath.'.go');
        @unlink($childResultPath);
    }
}
