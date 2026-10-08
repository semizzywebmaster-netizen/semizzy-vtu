<?php
namespace Addons\BankingFinancialIntegrations\Models;
use Illuminate\Database\Eloquent\Model;
class BankTransferBatch extends Model {
 protected $table='banking_transfer_batches';
 protected $guarded=[];
 protected $casts=['total_amount'=>'decimal:2','total_fee'=>'decimal:2','metadata'=>'array','processing_started_at'=>'datetime','completed_at'=>'datetime'];
}