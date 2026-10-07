<?php
namespace Addons\\VtuWebsiteBuilder\\Models;
use Illuminate\\Database\\Eloquent\\Model;
class WebsiteRevision extends Model {
 protected $table='website_revisions'; protected $guarded=[]; protected $casts=['content'=>'array'];
 public function site(){return $this->belongsTo(WebsiteSite::class,'website_site_id');}
 public function page(){return $this->belongsTo(WebsitePage::class,'website_page_id');}
}