<?php
namespace Addons\BankingFinancialIntegrations\Models;
use Illuminate\Database\Eloquent\Model;
class ScheduledBankTransfer extends Model {
 protected $table='banking_scheduled_transfers';
 protected $guarded=[];
 protected $casts=['scheduled_for'=>'datetime','processing_started_at'=>'datetime','completed_at'=>'datetime','cancelled_at'=>'datetime','total_amount'=>'decimal:2','total_fee'=>'decimal:2','metadata'=>'array'];
}