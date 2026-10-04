<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class VtuBulkOperation extends Model{
 protected $fillable=['uuid','reference','user_id','service_id','status','total_items','processed_items','successful_items','failed_items','metadata']; protected function casts():array{return ['metadata'=>'array'];}
 public function user():BelongsTo{return $this->belongsTo(User::class);} public function service():BelongsTo{return $this->belongsTo(Service::class);} public function items():HasMany{return $this->hasMany(VtuBulkOperationItem::class,'bulk_operation_id');}
}