<?php

namespace Tests\Feature;

use Tests\TestCase;

class AddonAutoloadRegistrationTest extends TestCase
{
    public function test_whatsapp_and_communication_addon_classes_are_autoloadable(): void
    {
        $this->assertTrue(class_exists(\Addons\WhatsAppBot\Observers\VtuTransactionObserver::class));
        $this->assertTrue(class_exists(\Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway::class));
        $this->assertTrue(class_exists(\Semizzy\Addons\Social\Models\SocialNumberInventory::class));
        $this->assertTrue(class_exists(\Semizzy\Addons\Social\Models\SocialServiceOrder::class));
        $this->assertTrue(class_exists(\Addons\BankingFinancialIntegrations\Models\AccountVerification::class));
        $this->assertTrue(class_exists(\Semizzy\Addons\GiftCards\Http\Controllers\Admin\GiftCardsController::class));
        $this->assertTrue(class_exists(\Semizzy\Addons\Government\Models\GovernmentApplication::class));
        $this->assertTrue(class_exists(\Addons\InsuranceProtection\Http\Controllers\InsuranceAdminController::class));
        $this->assertTrue(class_exists(\Semizzy\Addons\Smm\Models\SmmOrder::class));
    }
}
