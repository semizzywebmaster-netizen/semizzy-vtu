<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentBank extends Model {
 protected $table='payment_banks';
 protected $guarded=[];
 protected $casts=['active'=>'boolean','is_deleted'=>'boolean','pay_with_bank'=>'boolean','pay_with_bank_transfer'=>'boolean','metadata'=>'array','source_updated_at'=>'datetime'];
 public function scopeActive($query){return $query->where('active',true)->where('is_deleted',false);}
}
