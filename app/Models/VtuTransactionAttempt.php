<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class VtuTransactionAttempt extends Model{
 protected $fillable=['vtu_transaction_id','api_provider_id','attempt_number','operation','status','provider_reference','request_payload','response_payload','error_code','error_message','started_at','finished_at'];
 protected function casts():array{return ['request_payload'=>'array','response_payload'=>'array','started_at'=>'datetime','finished_at'=>'datetime'];}
 public function transaction():BelongsTo{return $this->belongsTo(VtuTransaction::class,'vtu_transaction_id');} public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');}
}