<?php
namespace Addons\\VtuWebsiteBuilder\\Models;
use Illuminate\\Database\\Eloquent\\Model;
class WebsiteSite extends Model {
 protected $table='website_sites'; protected $guarded=[]; protected $casts=['settings'=>'array'];
 public function user(){return $this->belongsTo(\\App\\Models\\User::class);}
 public function pages(){return $this->hasMany(WebsitePage::class,'website_site_id');}
 public function revisions(){return $this->hasMany(WebsiteRevision::class,'website_site_id');}
 public function domains(){return $this->hasMany(WebsiteDomain::class,'website_site_id');}
}