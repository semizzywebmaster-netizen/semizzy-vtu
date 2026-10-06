<?php
namespace AppModels;
use IlluminateDatabaseEloquentModel;
use IlluminateDatabaseEloquentRelationsBelongsTo;
class CacWebhookEvent extends Model {
 protected $fillable=['api_provider_id','event_id','event_type','signature','status','order_reference','payload','error_message','processed_at'];
 protected $casts=['payload'=>'array','processed_at'=>'datetime'];
 public function provider(): BelongsTo { return $this->belongsTo(ApiProvider::class,'api_provider_id'); }
}