<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PhoneChangeRequest extends Model {
 protected $fillable=['user_id','current_phone','requested_phone','reason','screenshot_path','status','admin_reason','reviewed_by','reviewed_at'];
 protected $casts=['reviewed_at'=>'datetime'];
 public function user(): BelongsTo{return $this->belongsTo(User::class);}
 public function reviewer(): BelongsTo{return $this->belongsTo(User::class,'reviewed_by');}
}