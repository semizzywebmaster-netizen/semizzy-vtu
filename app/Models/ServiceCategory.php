<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
 protected $fillable=['key','name','description','enabled','sort_order'];
 protected function casts():array{return ['enabled'=>'boolean'];}
 public function services():HasMany{return $this->hasMany(Service::class,'category_id');}
}
