<?php
namespace Semizzy\Addons\Escrow\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EscrowDispute extends Model {
 protected $table='escrow_disputes'; protected $guarded=[];
 protected function casts(): array { return ['resolved_at'=>'datetime']; }
 public function escrow(): BelongsTo { return $this->belongsTo(EscrowTransaction::class,'escrow_transaction_id'); }
 public function openedBy(): BelongsTo { return $this->belongsTo(User::class,'opened_by'); }
 public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class,'resolved_by'); }
}