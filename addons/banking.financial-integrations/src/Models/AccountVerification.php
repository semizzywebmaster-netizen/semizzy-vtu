<?php
namespace Addons\BankingFinancialIntegrations\Models;

use Illuminate\Database\Eloquent\Model;

class AccountVerification extends Model
{
 protected $table = 'banking_account_verifications';
 protected $guarded = [];
 protected $casts = ['verified_at'=>'datetime','response_meta'=>'array'];
}