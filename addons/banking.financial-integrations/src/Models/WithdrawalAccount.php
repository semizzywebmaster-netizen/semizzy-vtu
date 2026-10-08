<?php
namespace Addons\BankingFinancialIntegrations\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalAccount extends Model
{
 protected $table='banking_withdrawal_accounts';
 protected $guarded=[];
 protected $casts=['kyc_name_matched'=>'boolean','is_default'=>'boolean','active'=>'boolean','verified_at'=>'datetime'];
}
