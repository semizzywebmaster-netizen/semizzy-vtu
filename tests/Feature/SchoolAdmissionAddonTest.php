<?php

namespace Tests\\Feature;

use App\\Services\\Addons\\AddonRegistry;
use Tests\\TestCase;

final class SchoolAdmissionAddonTest extends TestCase
{
    public function test_school_admission_is_discovered_as_a_separate_nested_addon(): void
    {
        $manifest = app(AddonRegistry::class)->find('education.school-admission');

        $this->assertNotNull($manifest);
        $this->assertSame('education/school-admission', $manifest['source_path']);
        $this->assertSame('School Admission Services', $manifest['name']);
        $this->assertContains('school-admission.products.manage', $manifest['permissions']);
        $this->assertFileExists(base_path('addons/education/school-admission/database/migrations/2026_10_09_000001_create_school_admission_tables.php'));
        $this->assertFileExists(base_path('addons/education/school-admission/routes/web.php'));
        $this->assertFileExists(base_path('addons/education/school-admission/routes/admin.php'));
    }

    public function test_school_admission_schema_is_defined_without_seeding_fake_institutions_or_providers(): void
    {
        $migration = file_get_contents(base_path('addons/education/school-admission/database/migrations/2026_10_09_000001_create_school_admission_tables.php'));

        $this->assertStringContainsString("Schema::create('school_admission_institutions'", $migration);
        $this->assertStringContainsString("Schema::create('school_admission_programmes'", $migration);
        $this->assertStringContainsString("Schema::create('school_admission_products'", $migration);
        $this->assertStringContainsString("Schema::create('school_admission_transactions'", $migration);
        $this->assertStringNotContainsString('DB::table(', $migration);
        $this->assertStringNotContainsString('insert(', $migration);
    }
}
