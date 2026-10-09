<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void { DB::table('loan_products')->updateOrInsert(['key'=>'standard'],['name'=>'Standard Loan','currency'=>'NGN','minimum_amount_minor'=>1000,'maximum_amount_minor'=>100000000,'interest_rate'=>10,'tenure_days'=>30,'repayment_frequency'=>'monthly','late_penalty_rate'=>1,'active'=>true,'updated_at'=>now(),'created_at'=>now()]); }
 public function down(): void { DB::table('loan_products')->where('key','standard')->delete(); }
};