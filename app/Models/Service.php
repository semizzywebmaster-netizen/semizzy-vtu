<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

class Service extends Model
{
 protected $fillable=['category_id','key','name','description','enabled','metadata'];
 protected function casts():array{return ['enabled'=>'boolean','metadata'=>'array'];}
 public function category():BelongsTo{return $this->belongsTo(ServiceCategory::class,'category_id');}
 public function products():HasMany{return $this->hasMany(ServiceProduct::class);}
}
