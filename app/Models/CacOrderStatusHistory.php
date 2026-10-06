<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacOrderStatusHistory extends Model
{
 protected $fillable=['cac_order_id','from_status','to_status','actor_id','source','reason','metadata'];
 protected function casts(): array { return ['metadata'=>'array']; }
 public function order(): BelongsTo { return $this->belongsTo(CacOrder::class,'cac_order_id'); }
 public function actor(): BelongsTo { return $this->belongsTo(User::class,'actor_id'); }
}