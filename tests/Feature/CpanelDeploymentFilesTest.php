<?php

namespace Tests\Feature;

use Tests\TestCase;

class CpanelDeploymentFilesTest extends TestCase
{
    public function test_public_directory_has_apache_rewrite_rules_for_cpanel(): void
    {
        $path = public_path('.htaccess');

        $this->assertFileExists($path);
        $rules = file_get_contents($path);

        $this->assertIsString($rules);
        $this->assertStringContainsString('RewriteEngine On', $rules);
        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $rules);
        $this->assertStringContainsString('E=HTTP_AUTHORIZATION', $rules);
        $this->assertStringContainsString('Options -Indexes', $rules);
    }

    public function test_required_writable_runtime_directories_are_tracked(): void
    {
        $this->assertFileExists(base_path('storage/framework/views/.gitignore'));
        $this->assertFileExists(base_path('storage/logs/.gitignore'));
        $this->assertDirectoryExists(storage_path('framework/views'));
        $this->assertDirectoryExists(storage_path('logs'));
    }
}
