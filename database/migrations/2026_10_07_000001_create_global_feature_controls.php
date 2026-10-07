<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) return;
        // Feature definitions are stored in the existing settings registry as feature.<key> JSON.
        // Registering defaults here makes the controls immediately discoverable on existing installs.
        $service = app(\App\Services\System\FeatureControlService::class);
        foreach ([
            ['registration.enabled','User registration','Users','Allow new account registration.',true,[]],
            ['kyc.enabled','KYC','KYC','Allow KYC applications and verification.',true,[]],
            ['kyc.lookup.bvn','BVN verification lookup','KYC','Allow billable BVN lookups.',true,['kyc.enabled']],
            ['kyc.lookup.nin','NIN verification lookup','KYC','Allow billable NIN lookups.',true,['kyc.enabled']],
            ['finance.enabled','Wallet & finance','Finance','Allow wallet and financial operations.',true,[]],
            ['vtu.enabled','VTU services','VTU','Allow VTU service processing.',true,[]],
            ['support.enabled','Support centre','Communication','Allow support tickets.',true,[]],
            ['notifications.enabled','Notifications','Communication','Allow user notifications.',true,[]],
            ['api.enabled','API access','API','Allow API access.',true,[]],
            ['referrals.enabled','Referrals','Users','Allow referral features.',true,[]],
            ['referral.rewards.enabled','Referral rewards','Users','Allow referral rewards.',true,['referrals.enabled']],
            ['maintenance.enabled','Maintenance mode','System','Put ordinary users into maintenance mode.',false,[]],
        ] as $d) {
            $service->register($d[0], ['name'=>$d[1],'category'=>$d[2],'description'=>$d[3],'default_enabled'=>$d[4],'dependencies'=>$d[5]]);
        }
    }

    public function down(): void
    {
        // Feature definitions live in the shared settings registry. Do not delete
        // them on rollback because admins/addons may have registered additional keys.
    }
};
