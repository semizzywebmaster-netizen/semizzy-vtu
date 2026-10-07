<?php
namespace Tests\Feature;

use Tests\TestCase;

class EscrowAddonTest extends TestCase
{
    private function manifest(): array
    {
        return require base_path('addons/escrow.protection/manifest.php');
    }

    private function source(string $path): string
    {
        return file_get_contents(base_path($path));
    }

    public function test_escrow_manifest_is_present(): void
    {
        $this->assertFileExists(base_path('addons/escrow.protection/manifest.php'));
    }

    public function test_escrow_routes_and_lifecycle_contract_are_declared(): void
    {
        $m = $this->manifest();

        $this->assertContains('addons/escrow.protection/routes/web.php', $m['web_route_files']);
        $this->assertContains('addons/escrow.protection/routes/admin.php', $m['web_route_files']);
        $this->assertContains('addons/escrow.protection/routes/api.php', $m['api_route_files']);

        foreach ([
            'escrow.created',
            'escrow.funded',
            'escrow.released',
            'escrow.cancelled',
            'escrow.disputed',
            'escrow.refunded',
        ] as $event) {
            $this->assertContains($event, $m['events']);
        }

        $this->assertContains('escrow.expiry_reconciliation', $m['scheduled_tasks']);
    }

    public function test_escrow_web_actions_are_protected_by_addon_and_transaction_pin(): void
    {
        $routes = $this->source('addons/escrow.protection/routes/web.php');

        $this->assertStringContainsString('ensure.addon:escrow.protection', $routes);
        $this->assertSame(4, substr_count($routes, 'transaction.pin'));
        $this->assertStringContainsString('permission:escrow.release', $routes);
        $this->assertStringContainsString('permission:escrow.dispute', $routes);
        $this->assertStringContainsString('/escrow/{escrow}/cancel', $routes);
    }

    public function test_escrow_api_is_protected_by_addon_and_transaction_pin(): void
    {
        $routes = $this->source('addons/escrow.protection/routes/api.php');

        $this->assertStringContainsString('ensure.addon:escrow.protection', $routes);
        $this->assertStringContainsString('transaction.pin', $routes);
        $this->assertStringContainsString('permission:escrow.create', $routes);
        $this->assertStringContainsString('permission:escrow.release', $routes);
        $this->assertStringContainsString('permission:escrow.dispute', $routes);
        $this->assertStringContainsString('permission:escrow.refund', $routes);
    }

    public function test_escrow_service_has_idempotency_fee_and_lifecycle_safeguards(): void
    {
        $service = $this->source('addons/escrow.protection/src/Services/EscrowService.php');

        $this->assertStringContainsString('idempotency_key', $service);
        $this->assertStringContainsString('different escrow', $service);
        $this->assertStringContainsString('Escrow fees require a configured settlement wallet', $service);
        $this->assertStringContainsString('public function expire', $service);
        $this->assertStringContainsString('DB::afterCommit', $service);
        $this->assertStringContainsString('held_before_minor', $service);
        $this->assertStringContainsString('held_after_minor', $service);
    }

    public function test_escrow_api_resolution_requires_refund_permission(): void
    {
        $routes = $this->source('addons/escrow.protection/routes/api.php');

        $this->assertStringContainsString("Route::post('/api/v1/escrow/{escrow}/resolve'", $routes);
        $this->assertStringContainsString("->middleware('permission:escrow.refund')", $routes);
        $this->assertStringNotContainsString("resolve'])->middleware('permission:escrow.manage')", $routes);
    }

    public function test_escrow_creation_requires_an_active_seller_wallet(): void
    {
        $service = $this->source('addons/escrow.protection/src/Services/EscrowService.php');

        $this->assertStringContainsString('$sellerWallet=$wallets[$seller->id]??null;', $service);
        $this->assertStringContainsString("Seller must have an active NGN wallet.", $service);
    }

    public function test_escrow_admin_resolution_is_permission_gated(): void
    {
        $routes = $this->source('addons/escrow.protection/routes/admin.php');

        $this->assertStringContainsString('permission:escrow.refund', $routes);
        $this->assertStringContainsString("'/admin/escrow/{escrow}/resolve'", $routes);
    }
}
