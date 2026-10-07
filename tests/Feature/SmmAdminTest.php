<?php
namespace Tests\Feature;
use Tests\TestCase;
class SmmAdminTest extends TestCase {
 private function source(string $path):string{return file_get_contents(base_path($path));}
 public function test_admin_routes_support_service_catalogue_management():void{
  $r=$this->source('addons/smm.services/routes/admin.php');
  foreach(['permission:smm.services.manage','SmmService::create','/services/{service}','/services/{service}/toggle','mode','provider_api','manual'] as $x)$this->assertStringContainsString($x,$r);
 }
 public function test_admin_service_validation_enforces_quantity_bounds():void{
  $r=$this->source('addons/smm.services/routes/admin.php');
  $this->assertStringContainsString("'max_quantity'=>['required','integer','gte:min_quantity']",$r);
  $this->assertStringContainsString("'unit_price_minor'=>['required','integer','min:0']",$r);
 }
}