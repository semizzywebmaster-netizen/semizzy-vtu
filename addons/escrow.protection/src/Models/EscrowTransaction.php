<?php
namespace Semizzy\Addons\Escrow\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EscrowTransaction extends Model {
 protected $table='escrow_transactions'; protected $guarded=[];
 protected function casts(): array { return ['amount_minor'=>'string','fee_minor'=>'string','metadata'=>'array','expires_at'=>'datetime','funded_at'=>'datetime','released_at'=>'datetime','cancelled_at'=>'datetime','disputed_at'=>'datetime','refunded_at'=>'datetime']; }
 public function buyer(): BelongsTo { return $this->belongsTo(User::class,'buyer_id'); }
 public function seller(): BelongsTo { return $this->belongsTo(User::class,'seller_id'); }
}