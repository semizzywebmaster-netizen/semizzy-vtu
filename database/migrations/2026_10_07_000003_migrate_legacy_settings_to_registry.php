<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) return;

        $map = [
            'platform_name'=>'platform.name',
            'support_email'=>'platform.support_email',
            'support_notice'=>'platform.support_notice',
            'default_timezone'=>'platform.timezone',
            'theme_key'=>'appearance.theme_key',
            'theme_primary'=>'appearance.theme_primary',
            'skin_default'=>'appearance.skin_default',
            'kyc_bvn_lookup_charge_minor'=>'finance.kyc_bvn_lookup_charge_minor',
            'kyc_nin_lookup_charge_minor'=>'finance.kyc_nin_lookup_charge_minor',
            'theme_custom_light'=>'appearance.theme_custom_light',
            'theme_custom_dark'=>'appearance.theme_custom_dark',
            'business'=>'platform.business',
            'social'=>'platform.social',
            'assets'=>'appearance.assets',
            'footer_menu'=>'appearance.footer_menu',
            'smtp'=>'communication.smtp',
        ];

        foreach ($map as $legacy => $canonical) {
            $legacyRow = DB::table('system_settings')->where('key',$legacy)->first();
            if (!$legacyRow || DB::table('system_settings')->where('key',$canonical)->exists()) continue;

            DB::table('system_settings')->insert([
                'key'=>$canonical,
                'value'=>$legacyRow->value,
                'type'=>$legacyRow->type ?? 'string',
                'is_secret'=>(bool)($legacyRow->is_secret ?? false),
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['platform.name','platform.support_email','platform.support_notice','platform.timezone','appearance.theme_key','appearance.theme_primary','appearance.skin_default','finance.kyc_bvn_lookup_charge_minor','finance.kyc_nin_lookup_charge_minor','appearance.theme_custom_light','appearance.theme_custom_dark','platform.business','platform.social','appearance.assets','appearance.footer_menu','communication.smtp'] as $key) {
            DB::table('system_settings')->where('key',$key)->delete();
        }
    }
};
