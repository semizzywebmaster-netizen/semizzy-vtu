<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class VtuBulkOperationItem extends Model{
 protected $fillable=['bulk_operation_id','sequence','idempotency_key','vtu_transaction_id','recipient','product_id','amount_minor','status','error_message','metadata']; protected function casts():array{return ['amount_minor'=>'string','metadata'=>'array'];}
 public function bulk():BelongsTo{return $this->belongsTo(VtuBulkOperation::class,'bulk_operation_id');} public function transaction():BelongsTo{return $this->belongsTo(VtuTransaction::class,'vtu_transaction_id');} public function product():BelongsTo{return $this->belongsTo(ServiceProduct::class,'product_id');}
}