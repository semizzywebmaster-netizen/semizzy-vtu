<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommunicationCampaign extends Model {
 protected $fillable=['type','title','message','url','targets','channels','status','scheduled_at','sent_at','created_by'];
 protected function casts():array{return ['targets'=>'array','channels'=>'array','scheduled_at'=>'datetime','sent_at'=>'datetime'];}
 public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
}