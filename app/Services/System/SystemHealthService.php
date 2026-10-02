<?php

namespace App\Services\System;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Schema;

class SystemHealthService
{
    public function __construct(
        private DatabaseManager $database,
        private CacheRepository $cache,
    ) {}

    public function check(): array
    {
        $checks = [
            $this->phpCheck(),
            $this->extensionsCheck(),
            $this->appKeyCheck(),
            $this->databaseCheck(),
            $this->storageCheck(),
            $this->cacheCheck(),
            $this->queueCheck(),
            $this->appUrlCheck(),
        ];

        $failures = collect($checks)->where('status', 'FAIL')->count();
        $warnings = collect($checks)->where('status', 'WARN')->count();

        return [
            'status' => $failures > 0 ? 'FAIL' : ($warnings > 0 ? 'WARN' : 'PASS'),
            'checked_at' => now()->toISOString(),
            'checks' => $checks,
        ];
    }

    private function phpCheck(): array
    {
        $minimum = '8.3.0';
        $ok = version_compare(PHP_VERSION, $minimum, '>=');

        return $this->result(
            'php',
            'PHP runtime',
            $ok,
            $ok ? 'PHP '.PHP_VERSION.' meets the Core minimum.' : 'PHP '.PHP_VERSION.' is below the Core minimum of '.$minimum.'.',
        );
    }

    private function extensionsCheck(): array
    {
        $required = ['mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'ctype', 'json'];
        $missing = array_values(array_filter($required, fn (string $extension): bool => ! extension_loaded($extension)));
        $ok = $missing === [];

        return $this->result(
            'extensions',
            'Required PHP extensions',
            $ok,
            $ok ? 'All required extensions are loaded.' : 'Missing: '.implode(', ', $missing).'.',
        );
    }

    private function appKeyCheck(): array
    {
        $key = (string) config('app.key', '');
        $ok = $key !== '';

        return $this->result(
            'app_key',
            'Application encryption key',
            $ok,
            $ok ? 'Application key is configured.' : 'APP_KEY is not configured.',
        );
    }

    private function databaseCheck(): array
    {
        try {
            $this->database->connection()->getPdo();
            $hasMigrations = Schema::hasTable('migrations');

            return [
                'key' => 'database',
                'label' => 'Database connection',
                'status' => $hasMigrations ? 'PASS' : 'WARN',
                'message' => $hasMigrations
                    ? 'Database connection succeeded and the migrations table exists.'
                    : 'Database connection succeeded, but the migrations table does not exist yet.',
            ];
        } catch (\Throwable $exception) {
            return [
                'key' => 'database',
                'label' => 'Database connection',
                'status' => 'FAIL',
                'message' => 'Database connection failed. Check database configuration, credentials, network access, and permissions in the server environment.',
            ];
        }
    }

    private function storageCheck(): array
    {
        $paths = [
            storage_path(),
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        $pathLabels = [
            storage_path() => 'storage',
            storage_path('app') => 'storage/app',
            storage_path('framework') => 'storage/framework',
            storage_path('logs') => 'storage/logs',
            base_path('bootstrap/cache') => 'bootstrap/cache',
        ];
        $unwritable = array_values(array_map(
            fn (string $path): string => $pathLabels[$path] ?? 'required directory',
            array_filter($paths, fn (string $path): bool => ! is_dir($path) || ! is_writable($path)),
        ));
        $ok = $unwritable === [];

        return $this->result(
            'storage',
            'Writable application directories',
            $ok,
            $ok ? 'Required storage and bootstrap/cache directories are writable.' : 'Not writable: '.implode(', ', $unwritable).'.',
        );
    }

    private function cacheCheck(): array
    {
        $key = 'core-health-'.bin2hex(random_bytes(8));

        try {
            $this->cache->put($key, 'ok', 30);
            $value = $this->cache->get($key);
            $this->cache->forget($key);

            return $this->result(
                'cache',
                'Cache service',
                $value === 'ok',
                $value === 'ok' ? 'Cache read/write succeeded.' : 'Cache write/read did not return the expected value.',
            );
        } catch (\Throwable $exception) {
            return [
                'key' => 'cache',
                'label' => 'Cache service',
                'status' => 'FAIL',
                'message' => 'Cache check failed. Review the configured cache driver and storage permissions in the server environment.',
            ];
        }
    }

    private function queueCheck(): array
    {
        $driver = (string) config('queue.default', 'sync');

        if ($driver !== 'database') {
            return [
                'key' => 'queue',
                'label' => 'Queue configuration',
                'status' => 'WARN',
                'message' => 'Queue driver is '.$driver.'. Core is designed to support the database queue on cPanel.',
            ];
        }

        try {
            $hasJobs = Schema::hasTable('jobs');
            return $this->result(
                'queue',
                'Database queue',
                $hasJobs,
                $hasJobs ? 'Database queue table exists.' : 'Database queue is selected but the jobs table is missing.',
            );
        } catch (\Throwable $exception) {
            return [
                'key' => 'queue',
                'label' => 'Database queue',
                'status' => 'FAIL',
                'message' => 'Queue check failed. Review the queue configuration and database permissions in the server environment.',
            ];
        }
    }

    private function appUrlCheck(): array
    {
        $url = (string) config('app.url', '');
        $ok = filter_var($url, FILTER_VALIDATE_URL) !== false;

        return $this->result(
            'app_url',
            'Application URL',
            $ok,
            $ok ? 'APP_URL is configured.' : 'APP_URL is missing or invalid.',
        );
    }

    private function result(string $key, string $label, bool $ok, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $ok ? 'PASS' : 'FAIL',
            'message' => $message,
        ];
    }
}
