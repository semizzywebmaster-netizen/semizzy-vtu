<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SystemSettingsSmtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_multiple_smtp_profiles_with_encrypted_passwords(): void
    {
        $admin = User::factory()->create(['tier'=>5,'role'=>'ADMIN','email_verified_at'=>now()]);

        $this->actingAs($admin)->put('/admin/settings', [
            'platform_name'=>'Test Platform',
            'support_email'=>'support@example.com',
            'support_notice'=>'',
            'default_timezone'=>'Africa/Lagos',
            'theme_key'=>'modern-corporate',
            'theme_primary'=>'#2563EB',
            'skin_default'=>'light',
            'business'=>[],
            'social'=>[],
            'theme_custom_light'=>[],
            'theme_custom_dark'=>[],
            'smtp'=>[
                'enabled'=>true,
                'strategy'=>'failover',
                'profiles'=>[
                    [
                        'key'=>'sendpulse_1','name'=>'SendPulse','provider'=>'sendpulse','enabled'=>true,'priority'=>1,'weight'=>1,
                        'host'=>'smtp-pulse.com','port'=>587,'encryption'=>'tls','username'=>'smtp-user','password'=>'secret-one',
                        'from_address'=>'noreply@example.com','from_name'=>'Test Platform',
                    ],
                    [
                        'key'=>'gmail_1','name'=>'Gmail','provider'=>'gmail','enabled'=>true,'priority'=>2,'weight'=>1,
                        'host'=>'smtp.gmail.com','port'=>587,'encryption'=>'tls','username'=>'test@gmail.com','password'=>'secret-two',
                        'from_address'=>'test@gmail.com','from_name'=>'Test Platform',
                    ],
                ],
            ],
        ])->assertSessionHas('success');

        $raw = SystemSetting::query()->where('key','smtp')->value('value');
        $stored = json_decode($raw, true);

        $this->assertTrue($stored['enabled']);
        $this->assertCount(2, $stored['profiles']);
        $this->assertNotSame('secret-one', $stored['profiles'][0]['password']);
        $this->assertNotSame('secret-two', $stored['profiles'][1]['password']);
        $this->assertSame('secret-one', Crypt::decryptString($stored['profiles'][0]['password']));
        $this->assertSame('secret-two', Crypt::decryptString($stored['profiles'][1]['password']));
    }

    public function test_frontend_settings_do_not_expose_smtp_passwords(): void
    {
        $admin = User::factory()->create(['tier'=>5,'role'=>'ADMIN','email_verified_at'=>now()]);

        SystemSetting::query()->create([
            'key'=>'smtp',
            'value'=>json_encode([
                'enabled'=>true,'strategy'=>'failover','profiles'=>[[
                    'key'=>'custom_1','name'=>'Custom','provider'=>'custom','enabled'=>true,'priority'=>1,'weight'=>1,
                    'host'=>'mail.example.com','port'=>587,'encryption'=>'tls','username'=>'user',
                    'password'=>Crypt::encryptString('super-secret'),'from_address'=>'noreply@example.com','from_name'=>'Test',
                ]],
            ]),
            'type'=>'json',
            'is_secret'=>true,
        ]);

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('settings.smtp.profiles.0.username','user')
                ->missing('settings.smtp.profiles.0.password')
            );
    }
}
