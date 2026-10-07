<?php
namespace Tests\Feature;

use Tests\TestCase;

class SmmAddonTest extends TestCase
{
 private function source(string $path): string { return file_get_contents(base_path($path)); }

 public function test_smm_manifest_declares_provider_capabilities(): void {
  $m=require base_path('addons/smm.services/manifest.php');
  foreach(['smm_order','smm_status','smm_requery','smm_cancel'] as $cap) $this->assertContains($cap,$m['provider_capabilities']);
 }

 public function test_smm_order_routes_require_pin_and_throttling(): void {
  $routes=$this->source('addons/smm.services/routes/web.php');
  $this->assertStringContainsString('transaction.pin',$routes);
  $this->assertStringContainsString('throttle:20,1',$routes);
  $this->assertStringContainsString('throttle:10,1',$routes);
  $this->assertStringContainsString('permission:smm.orders.manage',$routes);
  $this->assertStringContainsString('permission:smm.cancel',$routes);
 }

 public function test_smm_service_uses_core_provider_operations_and_wallet_settlement(): void {
  $service=$this->source('addons/smm.services/src/Services/SmmOrderService.php');
  foreach(['smm_order','smm_requery','smm_cancel'] as $op) $this->assertStringContainsString($op,$service);
  $this->assertStringContainsString('wallets->reserve',$service);
  $this->assertStringContainsString('wallets->settle',$service);
  $this->assertStringContainsString('Provider reference is required before requery.',$service);
  $this->assertStringContainsString('Provider reference is required before cancellation.',$service);
 }

 public function test_smm_routes_protect_order_ownership_in_controller(): void {
  $controller=$this->source('app/Http/Controllers/SmmController.php');
  $this->assertStringContainsString('$order->user_id===(int)$r->user()->id',$controller);
  $this->assertStringContainsString('$service->requery($order)',$controller);
  $this->assertStringContainsString('$service->cancel($order)',$controller);
 }
}
