<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::table('savings_plans')->upsert([
            ['key'=>'flexible','name'=>'Flexible Savings','type'=>'flexible','minimum_amount_minor'=>100,'maximum_amount_minor'=>1000000000,'lock_days'=>0,'interest_rate'=>0,'early_withdrawal_penalty'=>0,'allow_early_withdrawal'=>true,'active'=>true,'metadata'=>json_encode([]),'created_at'=>$now,'updated_at'=>$now],
            ['key'=>'target','name'=>'Target Savings','type'=>'target','minimum_amount_minor'=>100,'maximum_amount_minor'=>1000000000,'lock_days'=>30,'interest_rate'=>0,'early_withdrawal_penalty'=>0,'allow_early_withdrawal'=>false,'active'=>true,'metadata'=>json_encode([]),'created_at'=>$now,'updated_at'=>$now],
            ['key'=>'fixed','name'=>'Fixed Savings','type'=>'fixed','minimum_amount_minor'=>1000,'maximum_amount_minor'=>1000000000,'lock_days'=>90,'interest_rate'=>0,'early_withdrawal_penalty'=>0,'allow_early_withdrawal'=>false,'active'=>true,'metadata'=>json_encode([]),'created_at'=>$now,'updated_at'=>$now],
        ], ['key'], ['name','type','minimum_amount_minor','maximum_amount_minor','lock_days','interest_rate','early_withdrawal_penalty','allow_early_withdrawal','active','metadata','updated_at']);
    }

    public function down(): void
    {
        DB::table('savings_plans')->whereIn('key',['flexible','target','fixed'])->delete();
    }
};