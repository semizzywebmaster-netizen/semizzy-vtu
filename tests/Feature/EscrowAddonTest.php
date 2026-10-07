<?php
namespace Tests\Feature;
use Tests\TestCase;
class EscrowAddonTest extends TestCase {
 public function test_escrow_manifest_is_present(): void { $this->assertFileExists(base_path('addons/escrow.protection/manifest.php')); }
 public function test_escrow_routes_are_declared(): void { $m=require base_path('addons/escrow.protection/manifest.php'); $this->assertContains('addons/escrow.protection/routes/web.php',$m['web_route_files']); $this->assertContains('addons/escrow.protection/routes/api.php',$m['api_route_files']); }
}