<?php
namespace Addons\\VtuWebsiteBuilder\\Services;
use Addons\\VtuWebsiteBuilder\\Models\\{WebsiteSite,WebsitePage,WebsiteRevision,WebsiteDomain};
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Str;
use Illuminate\\Validation\\ValidationException;
class WebsiteBuilderService {
 public function createSite(int $userId,array $data): WebsiteSite {
  return DB::transaction(function() use($userId,$data){
   $slug=Str::slug($data['slug']??$data['name']);
   $base=$slug; $i=2;
   while(WebsiteSite::where('user_id',$userId)->where('slug',$slug)->exists()) $slug=$base.'-'.$i++;
   $site=WebsiteSite::create(['user_id'=>$userId,'name'=>trim($data['name']),'slug'=>$slug,'template_key'=>$data['template_key']??'modern-corporate','settings'=>$data['settings']??[]]);
   WebsitePage::create(['website_site_id'=>$site->id,'title'=>'Home','slug'=>'home','status'=>'draft','is_home'=>true,'content'=>['sections'=>[]],'seo'=>[]]);
   return $site->load('pages');
  });
 }
 public function savePage(WebsiteSite $site,WebsitePage $page,array $data,?int $userId=null): WebsitePage {
  $version=((int)$site->revisions()->where('website_page_id',$page->id)->max('version'))+1;
  return DB::transaction(function() use($site,$page,$data,$userId,$version){
   $page->update(['title'=>trim($data['title']??$page->title),'content'=>$data['content']??[],'seo'=>$data['seo']??[],'status'=>'draft']);
   WebsiteRevision::create(['website_site_id'=>$site->id,'website_page_id'=>$page->id,'created_by'=>$userId,'version'=>$version,'status'=>'draft','content'=>$page->content]);
   if($site->status==='published') $site->update(['status'=>'draft']);
   return $page->fresh();
  });
 }
 public function publish(WebsiteSite $site): WebsiteSite {
  return DB::transaction(function() use($site){
   $site->load('pages');
   foreach($site->pages as $page) $page->update(['status'=>'published']);
   $site->update(['status'=>'published','published_revision_id'=>(string)optional($site->revisions()->latest('id')->first())->id]);
   return $site->fresh('pages','domains');
  });
 }
 public function addDomain(WebsiteSite $site,string $domain): WebsiteDomain {
  $domain=strtolower(trim($domain));
  $domain=preg_replace('/^https?:\\/\\//','',$domain); $domain=rtrim($domain,'/');
  if(!filter_var($domain,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) throw ValidationException::withMessages(['domain'=>'Enter a valid domain name.']);
  $existing=WebsiteDomain::where('domain',$domain)->first();
  if($existing && $existing->website_site_id!==$site->id) throw ValidationException::withMessages(['domain'=>'This domain is already registered.']);
  return $existing??WebsiteDomain::create(['website_site_id'=>$site->id,'domain'=>$domain,'type'=>'custom','status'=>'pending','verification_method'=>'dns_txt','verification_token'=>Str::random(40)]);
 }
 public function verifyDomain(WebsiteDomain $domain,string $token): WebsiteDomain {
  if(!hash_equals((string)$domain->verification_token,trim($token))) throw ValidationException::withMessages(['token'=>'Domain verification token is invalid.']);
  $domain->update(['status'=>'verified','verified_at'=>now(),'verification_token'=>null]);
  return $domain->fresh();
 }
}