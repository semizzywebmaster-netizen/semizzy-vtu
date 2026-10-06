<?php

namespace App\Services\System;

use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ZipArchive;
use RuntimeException;

class WebsiteBackupService
{
    private string $backupDirectory;

    public function __construct(private DatabaseManager $database, private Filesystem $files)
    {
        $this->backupDirectory = storage_path('app/backups');
    }

    public function create(): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('The PHP ZIP extension is required for website backups.');
        $this->files->ensureDirectoryExists($this->backupDirectory);
        $path = $this->backupDirectory.'/semizzy-one-backup_'.now()->format('Ymd_His').'_'.Str::lower(Str::random(10)).'.zip';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Backup archive could not be created.');

        $zip->addFromString('manifest.json', json_encode([
            'format'=>'semizzy-one-backup','version'=>1,'created_at'=>now()->toISOString(),
            'database_driver'=>(string)config('database.default'),'includes'=>['database','storage/app/public'],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $databaseFile = tempnam(sys_get_temp_dir(), 'semizzy-db-');
        if ($databaseFile === false) throw new RuntimeException('Temporary backup storage could not be created.');
        $handle = fopen($databaseFile, 'wb');
        if ($handle === false) { @unlink($databaseFile); throw new RuntimeException('Temporary database backup could not be opened.'); }

        try {
            foreach ($this->databaseTables() as $table) {
                fwrite($handle, json_encode(['table'=>$table], JSON_THROW_ON_ERROR)."\n");
                $this->database->table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($handle, $table): void {
                    $payload = [];
                    foreach ($rows as $row) $payload[] = (array)$row;
                    if ($payload) fwrite($handle, json_encode(['table'=>$table,'rows'=>$payload], JSON_THROW_ON_ERROR)."\n");
                });
            }
            fclose($handle);
            $zip->addFile($databaseFile, 'database.jsonl');
            $this->addDirectoryToZip($zip, storage_path('app/public'), 'storage/app/public');
            if ($zip->close() !== true) throw new RuntimeException('Backup archive could not be finalized.');
        } finally {
            if (is_resource($handle)) fclose($handle);
            @unlink($databaseFile);
        }
        return $path;
    }

    public function restore(string $archivePath): void
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('The PHP ZIP extension is required for website restore.');
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) throw new RuntimeException('The uploaded backup is not a valid ZIP archive.');
        try {
            $manifest = json_decode((string)$zip->getFromName('manifest.json'), true);
            if (!is_array($manifest) || ($manifest['format'] ?? null) !== 'semizzy-one-backup' || (int)($manifest['version'] ?? 0) !== 1) {
                throw new RuntimeException('This backup was not created by the SEMIZZY ONE backup system.');
            }
            $stream = $zip->getStream('database.jsonl');
            if (!is_resource($stream)) throw new RuntimeException('Backup database data is missing.');
            $this->restoreDatabase($stream);
            fclose($stream);
            $this->restorePublicStorage($zip);
        } finally {
            $zip->close();
        }
    }

    private function databaseTables(): array
    {
        $connection = $this->database->connection();
        return match ($connection->getDriverName()) {
            'mysql' => collect($connection->select('SHOW TABLES'))->map(fn($r)=>(array)$r)->map(fn($r)=>(string)array_values($r)[0])->values()->all(),
            'sqlite' => collect($connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name')->values()->all(),
            default => throw new RuntimeException('Backups currently support MySQL/MariaDB and SQLite.'),
        };
    }

    private function restoreDatabase($stream): void
    {
        $connection = $this->database->connection();
        $driver = $connection->getDriverName();
        if ($driver === 'mysql') $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        if ($driver === 'sqlite') $connection->statement('PRAGMA foreign_keys=OFF');

        try {
            $known = array_flip($this->databaseTables());
            $cleared = [];
            $connection->beginTransaction();
            while (($line = fgets($stream)) !== false) {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $table = $record['table'] ?? null;
                if (!is_string($table) || !isset($known[$table])) continue;
                if (!isset($cleared[$table])) {
                    $connection->table($table)->delete();
                    $cleared[$table] = true;
                }
                $rows = $record['rows'] ?? [];
                if (is_array($rows) && $rows) {
                    foreach (array_chunk($rows, 500) as $chunk) $connection->table($table)->insert($chunk);
                }
            }
            $connection->commit();
        } catch (\Throwable $e) {
            if ($connection->transactionLevel() > 0) $connection->rollBack();
            throw $e;
        } finally {
            if ($driver === 'mysql') $connection->statement('SET FOREIGN_KEY_CHECKS=1');
            if ($driver === 'sqlite') $connection->statement('PRAGMA foreign_keys=ON');
        }
    }

    private function restorePublicStorage(ZipArchive $zip): void
    {
        $prefix='storage/app/public/'; $destination=storage_path('app/public');
        $this->files->ensureDirectoryExists($destination);
        for($i=0;$i<$zip->numFiles;$i++){
            $entry=$zip->getNameIndex($i);
            if(!is_string($entry)||!Str::startsWith($entry,$prefix)||Str::endsWith($entry,'/')) continue;
            $relative=Str::after($entry,$prefix);
            if($relative===''||str_contains($relative,'..')||Str::startsWith($relative,'/')) continue;
            $target=$destination.'/'.$relative;
            $this->files->ensureDirectoryExists(dirname($target));
            $contents=$zip->getFromIndex($i);
            if($contents!==false) file_put_contents($target,$contents,LOCK_EX);
        }
    }

    private function addDirectoryToZip(ZipArchive $zip,string $directory,string $prefix):void
    {
        if(!is_dir($directory)) return;
        foreach($this->files->allFiles($directory) as $file){
            $relative=Str::after($file->getPathname(),rtrim($directory,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
            if(!str_contains($relative,'..')) $zip->addFile($file->getPathname(),$prefix.'/'.$relative);
        }
    }
}
