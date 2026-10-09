<?php

namespace Tests\Feature;

use Tests\TestCase;

class AddonAutoloadRegistrationTest extends TestCase
{
    public function test_whatsapp_and_communication_addon_classes_are_autoloadable(): void
    {
        $this->assertTrue(class_exists(\Addons\WhatsAppBot\Observers\VtuTransactionObserver::class));
        $this->assertTrue(class_exists(\Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway::class));
    }
}
