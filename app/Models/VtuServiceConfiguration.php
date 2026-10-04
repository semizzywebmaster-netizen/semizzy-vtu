<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class VtuServiceConfiguration extends Model{
 protected $fillable=['service_id','key','value','is_secret','enabled']; protected function casts():array{return ['value'=>'array','is_secret'=>'boolean','enabled'=>'boolean'];} public function service():BelongsTo{return $this->belongsTo(Service::class);}
}