<?php
namespace Addons\VtuWebsiteBuilder\Models;
use Illuminate\Database\Eloquent\Model;
class WebsitePage extends Model {
 protected $table='website_pages'; protected $guarded=[]; protected $casts=['content'=>'array','seo'=>'array','is_home'=>'boolean'];
 public function site(){return $this->belongsTo(WebsiteSite::class,'website_site_id');}
 public function revisions(){return $this->hasMany(WebsiteRevision::class,'website_page_id');}
}