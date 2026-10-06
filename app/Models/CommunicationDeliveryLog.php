<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationDeliveryLog extends Model
{
    protected $fillable = [
        'campaign_id','user_id','channel','status','provider_id','provider_reference',
        'attempts','error_message','queued_at','sent_at','failed_at',
    ];

    protected function casts(): array
    {
        return [
            'queued_at'=>'datetime',
            'sent_at'=>'datetime',
            'failed_at'=>'datetime',
        ];
    }

    public function campaign(): BelongsTo { return $this->belongsTo(CommunicationCampaign::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function provider(): BelongsTo { return $this->belongsTo(ApiProvider::class); }
}
