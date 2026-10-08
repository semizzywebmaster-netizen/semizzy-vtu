<?php
namespace Addons\BankingFinancialIntegrations\Models;

use Illuminate\Database\Eloquent\Model;

class BankingTransfer extends Model
{
 protected $table = 'banking_transfers';
 protected $guarded = [];
 protected $casts = [
  'amount'=>'decimal:2',
  'fee'=>'decimal:2',
  'processing_started_at'=>'datetime',
  'completed_at'=>'datetime',
  'reversed_at'=>'datetime',
  'metadata'=>'array',
 ];
}