<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class VtuTransaction extends Model{
 protected $fillable=['uuid','reference','user_id','service_id','service_product_id','api_provider_id','financial_operation_id','idempotency_key','status','amount_minor','fee_minor','total_minor','currency','customer_tier','recipient','request_payload','response_payload','provider_reference','provider_status','failure_code','failure_message','processed_at','completed_at','metadata'];
 protected function casts():array{return ['amount_minor'=>'string','fee_minor'=>'string','total_minor'=>'string','request_payload'=>'array','response_payload'=>'array','metadata'=>'array','processed_at'=>'datetime','completed_at'=>'datetime'];}
 public function user():BelongsTo{return $this->belongsTo(User::class);} public function service():BelongsTo{return $this->belongsTo(Service::class);} public function product():BelongsTo{return $this->belongsTo(ServiceProduct::class,'service_product_id');} public function provider():BelongsTo{return $this->belongsTo(ApiProvider::class,'api_provider_id');} public function financialOperation():BelongsTo{return $this->belongsTo(FinancialOperation::class);} public function attempts():HasMany{return $this->hasMany(VtuTransactionAttempt::class);} public function isTerminal():bool{return in_array($this->status,['successful','failed','reversed','cancelled'],true);}
}