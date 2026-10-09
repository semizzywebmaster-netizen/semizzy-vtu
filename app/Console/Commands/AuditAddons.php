<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

class AuditAddons extends Command
{
    protected $signature = 'addons:audit {--json : Output the audit report as JSON}';
    protected $description = 'Read-only audit of addon manifests, paths, dependencies, routes, and migrations.';

    public function handle(): int
    {
        $root = realpath(base_path('addons'));
        if ($root === false || !is_dir($root)) {
            $this->error('Addon root directory does not exist.');
            return self::FAILURE;
        }

        $files = array_merge(glob($root.'/*/manifest.php') ?: [], glob($root.'/*/*/manifest.php') ?: []);
        sort($files, SORT_STRING);

        $issues = [];
        $manifests = [];
        $ids = [];
        foreach ($files as $file) {
            $real = realpath($file);
            if ($real === false || !$this->within($real, $root)) {
                $issues[] = $this->issue('error', 'unsafe_manifest_path', $file, 'Manifest resolves outside addon root.');
                continue;
            }

            try {
                $manifest = require $real;
            } catch (Throwable $e) {
                $issues[] = $this->issue('error', 'manifest_load_failed', $this->relative($real), $e->getMessage());
                continue;
            }

            if (!is_array($manifest)) {
                $issues[] = $this->issue('error', 'invalid_manifest', $this->relative($real), 'Manifest must return an array.');
                continue;
            }

            $relative = $this->relative(dirname($real));
            $requiredMissing = [];
            foreach (['identifier', 'name', 'version'] as $key) {
                if (!isset($manifest[$key]) || !is_string($manifest[$key]) || trim($manifest[$key]) === '') $requiredMissing[] = $key;
            }
            if ($requiredMissing !== []) {
                $issues[] = $this->issue('error', 'missing_manifest_fields', $relative, 'Missing or empty: '.implode(', ', $requiredMissing));
                continue;
            }

            $id = strtolower(trim($manifest['identifier']));
            if (isset($ids[$id])) {
                $issues[] = $this->issue('error', 'duplicate_identifier', $relative, "Identifier [{$id}] also appears in [{$ids[$id]}].");
            } else {
                $ids[$id] = $relative;
            }

            $manifest['_audit_source_path'] = $relative;
            $manifests[$id] = $manifest;
        }

        $migrationOwners = [];
        foreach ($manifests as $id => $manifest) {
            $source = (string) $manifest['_audit_source_path'];
            $this->auditRoutes($manifest, $source, $root, $issues);
            $this->auditMigrations($manifest, $source, $root, $migrationOwners, $issues);
            $this->auditInstaller($manifest, $source, $issues);
            $dependencies = $manifest['dependencies'] ?? [];
            if (!is_array($dependencies)) {
                $issues[] = $this->issue('error', 'invalid_dependencies', $source, 'Dependencies must be an array.');
                continue;
            }
            foreach ($dependencies as $dependency) {
                $depId = is_string($dependency) ? strtolower(trim($dependency)) : strtolower(trim((string) ($dependency['identifier'] ?? '')));
                if ($depId !== '' && !isset($ids[$depId])) {
                    $issues[] = $this->issue('warning', 'unresolved_dependency', $source, "Dependency [{$depId}] has no discovered addon manifest; it may be a Core capability.");
                }
            }
        }

        $educationRoot = $root.'/education';
        if (is_dir($educationRoot) && !is_file($educationRoot.'/manifest.php')) {
            $issues[] = $this->issue('warning', 'category_directory_without_manifest', 'education', 'Directory has no root manifest; investigate its contents before treating it as an installable addon.');
        }

        $summary = [
            'addon_root' => $root,
            'manifest_count' => count($manifests),
            'error_count' => count(array_filter($issues, static fn ($issue) => $issue['severity'] === 'error')),
            'warning_count' => count(array_filter($issues, static fn ($issue) => $issue['severity'] === 'warning')),
            'issues' => $issues,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info("Addon manifests discovered: {$summary['manifest_count']}");
            $this->line("Errors: {$summary['error_count']} | Warnings: {$summary['warning_count']}");
            foreach ($issues as $issue) {
                $line = strtoupper($issue['severity'])." [{$issue['code']}] {$issue['path']}: {$issue['message']}";
                $issue['severity'] === 'error' ? $this->error($line) : $this->warn($line);
            }
            if ($issues === []) $this->info('No addon structure issues detected.');
            $this->comment('Read-only audit: no files, database records, or addon states were changed.');
        }

        return $summary['error_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function auditRoutes(array $manifest, string $source, string $root, array &$issues): void
    {
        foreach (['web_route_files', 'api_route_files'] as $key) {
            $entries = $manifest[$key] ?? [];
            if (!is_array($entries)) {
                $issues[] = $this->issue('error', 'invalid_route_list', $source, "{$key} must be an array.");
                continue;
            }
            foreach ($entries as $entry) {
                if (!is_string($entry) || trim($entry) === '') {
                    $issues[] = $this->issue('error', 'invalid_route_entry', $source, "Invalid entry in {$key}.");
                    continue;
                }
                $path = str_replace('\\', '/', trim($entry));
                if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) || in_array('..', explode('/', $path), true) || !str_starts_with($path, 'addons/')) {
                    $issues[] = $this->issue('error', 'unsafe_route_path', $source, "Unsafe route declaration [{$entry}].");
                    continue;
                }
                $candidates = [base_path($path)];
                $prefix = 'addons/'.basename($source).'/';
                if (str_starts_with($path, $prefix)) $candidates[] = base_path('addons/'.$source.'/'.substr($path, strlen($prefix)));
                $found = false;
                foreach ($candidates as $candidate) {
                    $real = realpath($candidate);
                    if ($real !== false && is_file($real) && $this->within($real, $root)) { $found = true; break; }
                }
                if (!$found) $issues[] = $this->issue('error', 'missing_route_file', $source, "Route file not found inside addon root: {$entry}");
            }
        }
    }

    private function auditMigrations(array $manifest, string $source, string $root, array &$owners, array &$issues): void
    {
        $entries = $manifest['migrations'] ?? [];
        if (!is_array($entries)) {
            $issues[] = $this->issue('error', 'invalid_migrations', $source, 'Migrations must be an array.');
            return;
        }
        foreach ($entries as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                $issues[] = $this->issue('error', 'invalid_migration_entry', $source, 'Migration entries must be non-empty strings.');
                continue;
            }
            $name = basename(str_replace('\\', '/', trim($entry)));
            if (!str_ends_with(strtolower($name), '.php')) $name .= '.php';
            if (!preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[A-Za-z0-9_]+\.php$/', $name)) {
                $issues[] = $this->issue('error', 'invalid_migration_filename', $source, "Invalid migration filename [{$name}].");
                continue;
            }

            $candidates = array_merge(
                glob($root.'/*/database/migrations/'.$name) ?: [],
                glob($root.'/*/*/database/migrations/'.$name) ?: [],
            );
            $matches = [];
            foreach ($candidates as $candidate) {
                $real = realpath($candidate);
                if ($real !== false && is_file($real) && $this->within($real, $root)) $matches[$real] = $real;
            }
            if (count($matches) > 1) {
                $issues[] = $this->issue('error', 'ambiguous_migration', $source, "Migration [{$name}] exists in multiple addon directories.");
                continue;
            }

            $corePath = base_path('database/migrations/'.$name);
            $exists = $matches !== [] || is_file($corePath);
            if (!$exists) {
                $issues[] = $this->issue('error', 'missing_migration', $source, "Migration file [{$name}] was not found in addon or Core migration directories.");
                continue;
            }
            $owners[$name][] = $source;
        }
    }

    private function auditInstaller(array $manifest, string $source, array &$issues): void
    {
        if (!isset($manifest['installer'])) return;
        if (!is_string($manifest['installer']) || trim($manifest['installer']) === '') {
            $issues[] = $this->issue('error', 'invalid_installer', $source, 'Installer must be a non-empty class name.');
            return;
        }
        if (!class_exists($manifest['installer'])) {
            $issues[] = $this->issue('warning', 'installer_class_not_loaded', $source, "Installer class [{$manifest['installer']}] is not currently autoloadable; check namespace and addon autoload configuration.");
        }
    }

    private function issue(string $severity, string $code, string $path, string $message): array
    {
        return compact('severity', 'code', 'path', 'message');
    }

    private function relative(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        return str_starts_with($path, $base) ? str_replace('\\', '/', substr($path, strlen($base))) : $path;
    }

    private function within(string $path, string $root): bool
    {
        return str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
}
