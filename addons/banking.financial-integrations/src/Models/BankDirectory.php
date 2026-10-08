<?php
namespace Addons\BankingFinancialIntegrations\Models;

use Illuminate\Database\Eloquent\Model;

class BankDirectory extends Model
{
 protected $table = 'banking_bank_directories';
 protected $guarded = [];
 protected $casts = ['active'=>'boolean','metadata'=>'array'];
}