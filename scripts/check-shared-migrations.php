<?php

declare(strict_types=1);

/**
 * The share/ directory contains distributable copies of core migrations.
 * Keep each copy byte-for-byte aligned with its canonical database/migrations file.
 */

$sharedMigrations = glob(__DIR__ . '/../share/*.php') ?: [];
$canonicalDirectory = __DIR__ . '/../database/migrations';
$drift = [];

foreach ($sharedMigrations as $sharedPath) {
    $name = basename($sharedPath);
    $canonicalPath = $canonicalDirectory . '/' . $name;

    if (! is_file($canonicalPath)) {
        $drift[] = sprintf('%s has no canonical migration at database/migrations/%s', $name, $name);
        continue;
    }

    if (! hash_equals(hash_file('sha256', $canonicalPath), hash_file('sha256', $sharedPath))) {
        $drift[] = sprintf('%s differs from its canonical migration', $name);
    }
}

if ($drift !== []) {
    fwrite(STDERR, "Shared migration copies are out of sync:\n - " . implode("\n - ", $drift) . "\n");
    exit(1);
}

printf("Shared migration copies are synchronized (%d files checked).\n", count($sharedMigrations));
