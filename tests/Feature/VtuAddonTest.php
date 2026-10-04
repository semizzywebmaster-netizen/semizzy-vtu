<?php
namespace Tests\Feature;
use App\Models\Service;
use App\Services\Vtu\VtuServiceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class VtuAddonTest extends TestCase{
 use RefreshDatabase;
 public function test_registry_bootstraps_all_declared_vtu_services_disabled_by_default():void{
  app(VtuServiceRegistry::class)->bootstrapCatalogue();
  $this->assertCount(count(VtuServiceRegistry::MANIFEST),Service::query()->whereIn('key',array_keys(VtuServiceRegistry::MANIFEST))->get());
  foreach(VtuServiceRegistry::MANIFEST as $key=>$name){
   $s=Service::where('key',$key)->firstOrFail();
   $this->assertSame($name,$s->name);$this->assertFalse((bool)$s->enabled);$this->assertSame('vtu.digital-services',$s->metadata['addon']);
  }
 }
 public function test_registry_field_contract_covers_each_service():void{
  $r=app(VtuServiceRegistry::class);
  foreach(VtuServiceRegistry::MANIFEST as $key=>$_)$this->assertNotEmpty($r->fields($key),$key.' must declare required fields');
 }
}