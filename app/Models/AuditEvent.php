<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    protected $fillable = ['actor_id','event','auditable_type','auditable_id','request_id','ip_address','user_agent','context'];

    protected function casts(): array { return ['context' => 'array']; }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit events are append-only and cannot be modified.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit events are append-only and cannot be deleted.');
        });
    }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
