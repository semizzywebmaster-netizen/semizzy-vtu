<?php
namespace Addons\\VtuWebsiteBuilder\\Models;
use Illuminate\\Database\\Eloquent\\Model;
class WebsiteDomain extends Model {
 protected $table='website_domains'; protected $guarded=[]; protected $casts=['metadata'=>'array','verified_at'=>'datetime','primary'=>'boolean'];
 public function site(){return $this->belongsTo(WebsiteSite::class,'website_site_id');}
}