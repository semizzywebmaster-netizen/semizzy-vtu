<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up(): void {
  $rows = [
   ['044','Access Bank Plc','Access Bank','commercial_bank'],
   ['050','Ecobank Nigeria Limited','Ecobank','commercial_bank'],
   ['070','Fidelity Bank Plc','Fidelity','commercial_bank'],
   ['011','First Bank of Nigeria Limited','FirstBank','commercial_bank'],
   ['214','First City Monument Bank Limited','FCMB','commercial_bank'],
   ['103','Globus Bank Limited','Globus Bank','commercial_bank'],
   ['058','Guaranty Trust Bank Limited','GTBank','commercial_bank'],
   ['082','Keystone Bank Limited','Keystone','commercial_bank'],
   ['076','Polaris Bank Limited','Polaris','commercial_bank'],
   ['039','Stanbic IBTC Bank Limited','Stanbic IBTC','commercial_bank'],
   ['068','Standard Chartered Bank Nigeria Limited','Standard Chartered','commercial_bank'],
   ['232','Sterling Bank Limited','Sterling','commercial_bank'],
   ['250','Titan Trust Bank Limited','Titan Trust','commercial_bank'],
   ['032','Union Bank of Nigeria Plc','Union Bank','commercial_bank'],
   ['033','United Bank for Africa Plc','UBA','commercial_bank'],
   ['215','Unity Bank Plc','Unity Bank','commercial_bank'],
   ['035','Wema Bank Plc','Wema Bank','commercial_bank'],
   ['057','Zenith Bank Plc','Zenith','commercial_bank'],
   ['023','Citibank Nigeria Limited','Citi','commercial_bank'],
   ['100','SunTrust Bank Nigeria Limited','SunTrust','commercial_bank'],
   ['101','Providus Bank Limited','Providus','commercial_bank'],
   ['301','Jaiz Bank Plc','Jaiz','non_interest_bank'],
   ['50515','Moniepoint Microfinance Bank','Moniepoint','wallet_app'],
   ['090267','Kuda Microfinance Bank','Kuda','wallet_app'],
   ['999992','OPay','OPay','wallet_app'],
   ['999991','PalmPay','PalmPay','wallet_app'],
   ['327','Paga','Paga','wallet_app'],
   ['090270','Carbon','Carbon','wallet_app'],
  ];
  foreach ($rows as [$code,$name,$short,$type]) {
   DB::table('banking_bank_directories')->updateOrInsert(
    ['bank_code'=>$code,'country_code'=>'NG'],
    ['name'=>$name,'short_name'=>$short,'institution_type'=>$type,'institution_category'=>$type==='commercial_bank'?'Commercial Bank':($type==='non_interest_bank'?'Non-Interest Bank':'Digital/Mobile Wallet'),'institution_identifier'=>strtolower(preg_replace('/[^a-z0-9]+/i','-', $short)),'active'=>true,'currency'=>'NGN','updated_at'=>now(),'created_at'=>now()]
   );
  }
 }
 public function down(): void {}
};
