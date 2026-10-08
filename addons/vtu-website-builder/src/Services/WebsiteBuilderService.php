<?php
namespace Addons\VtuWebsiteBuilder\Services;

use Addons\VtuWebsiteBuilder\Models\{WebsiteSite,WebsitePage,WebsiteRevision,WebsiteDomain};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteBuilderService
{
    private const SECTION_TYPES = [
        'hero','text','image','features','pricing','services','testimonials','faq','cta','contact','footer'
    ];

    public function createSite(int $userId,array $data): WebsiteSite
    {
        return DB::transaction(function() use($userId,$data){
            $slug=Str::slug($data['slug']??$data['name']);
            $base=$slug; $i=2;
            while(WebsiteSite::where('user_id',$userId)->where('slug',$slug)->exists()) $slug=$base.'-'.$i++;
            $site=WebsiteSite::create([
                'user_id'=>$userId,'name'=>trim($data['name']),'slug'=>$slug,
                'template_key'=>$data['template_key']??'modern-corporate','settings'=>$data['settings']??[]
            ]);
            WebsitePage::create([
                'website_site_id'=>$site->id,'title'=>'Home','slug'=>'home','status'=>'draft',
                'is_home'=>true,'sort_order'=>0,'content'=>['sections'=>[]],'seo'=>[]
            ]);
            return $site->load('pages');
        });
    }

    public function updateSite(WebsiteSite $site, array $data): WebsiteSite
    {
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $settings = array_replace_recursive($site->settings ?? [], $settings);
        foreach (['theme','branding','seo'] as $key) {
            $settings[$key] = is_array($settings[$key] ?? null) ? $settings[$key] : [];
        }
        $wasPublished = $site->status === 'published';
        $site->update([
            'name' => trim($data['name'] ?? $site->name),
            'template_key' => $data['template_key'] ?? $site->template_key,
            'settings' => $settings,
            'status' => $wasPublished ? 'draft' : $site->status,
            'published_at' => $wasPublished ? null : $site->published_at,
        ]);
        return $site->fresh(['pages','domains']);
    }

    public function resolvePublishedByHost(string $host): ?WebsiteSite
    {
        $host = strtolower(trim(explode(':', $host)[0]));
        return WebsiteSite::where('status','published')
            ->where(function($q) use ($host) {
                $q->where('active_domain',$host)
                  ->orWhere('subdomain',$host)
                  ->orWhereHas('domains', fn($d) => $d->where('domain',$host)->where('status','verified'));
            })->first();
    }

    public function createPage(WebsiteSite $site,array $data): WebsitePage
    {
        $slug=Str::slug($data['slug']??$data['title']);
        $base=$slug ?: 'page'; $i=2;
        while($site->pages()->where('slug',$slug)->exists()) $slug=$base.'-'.$i++;
        $order=((int)$site->pages()->max('sort_order'))+1;
        return $site->pages()->create([
            'title'=>trim($data['title']),'slug'=>$slug,'status'=>'draft','is_home'=>false,
            'sort_order'=>$order,'content'=>['sections'=>[]],'seo'=>$data['seo']??[]
        ]);
    }

    public function savePage(WebsiteSite $site,WebsitePage $page,array $data,?int $userId=null): WebsitePage
    {
        $content=$this->normalizeContent($data['content']??($page->content??[]));
        $version=((int)$site->revisions()->where('website_page_id',$page->id)->max('version'))+1;
        return DB::transaction(function() use($site,$page,$data,$content,$userId,$version){
            $page->update([
                'title'=>trim($data['title']??$page->title),
                'content'=>$content,'seo'=>$data['seo']??($page->seo??[]),'status'=>'draft'
            ]);
            WebsiteRevision::create([
                'website_site_id'=>$site->id,'website_page_id'=>$page->id,'created_by'=>$userId,
                'version'=>$version,'status'=>'draft','content'=>$page->content
            ]);
            if($site->status==='published') $site->update(['status'=>'draft','published_at'=>null]);
            return $page->fresh();
        });
    }

    public function addSection(WebsitePage $page,string $type,array $data=[]): WebsitePage
    {
        if(!in_array($type,self::SECTION_TYPES,true)) throw ValidationException::withMessages(['type'=>'Unsupported website section.']);
        $content=$this->normalizeContent($page->content??[]);
        $content['sections'][]=['id'=>(string)Str::uuid(),'type'=>$type,'data'=>$data];
        $page->update(['content'=>$content,'status'=>'draft']);
        return $page->fresh();
    }

    public function updateSection(WebsitePage $page,string $sectionId,string $type,array $data=[]): WebsitePage
    {
        if(!in_array($type,self::SECTION_TYPES,true)) throw ValidationException::withMessages(['type'=>'Unsupported website section.']);
        $content=$this->normalizeContent($page->content??[]);
        foreach($content['sections'] as &$section){
            if($section['id']===$sectionId){$section=['id'=>$sectionId,'type'=>$type,'data'=>$data];break;}
        }
        unset($section);
        $page->update(['content'=>$content,'status'=>'draft']);
        return $page->fresh();
    }

    public function deleteSection(WebsitePage $page,string $sectionId): WebsitePage
    {
        $content=$this->normalizeContent($page->content??[]);
        $content['sections']=array_values(array_filter($content['sections'],fn($s)=>$s['id']!==$sectionId));
        $page->update(['content'=>$content,'status'=>'draft']);
        return $page->fresh();
    }

    public function reorderSections(WebsitePage $page,array $sectionIds): WebsitePage
    {
        $content=$this->normalizeContent($page->content??[]);
        $byId=[]; foreach($content['sections'] as $section) $byId[$section['id']]=$section;
        if(count($sectionIds)!==count($byId) || count(array_unique($sectionIds))!==count($sectionIds) || count(array_intersect($sectionIds,array_keys($byId)))!==count($byId))
            throw ValidationException::withMessages(['sections'=>'Section order is invalid.']);
        $content['sections']=array_map(fn($id)=>$byId[$id],$sectionIds);
        $page->update(['content'=>$content,'status'=>'draft']);
        return $page->fresh();
    }

    public function deletePage(WebsiteSite $site,WebsitePage $page): void
    {
        if($site->pages()->count()<=1) throw ValidationException::withMessages(['page'=>'A website must keep at least one page.']);
        if($page->is_home) throw ValidationException::withMessages(['page'=>'Set another page as Home before deleting this page.']);
        $page->delete();
    }

    public function setHomePage(WebsiteSite $site,WebsitePage $page): WebsitePage
    {
        return DB::transaction(function() use($site,$page){
            $site->pages()->update(['is_home'=>false]);
            $page->update(['is_home'=>true]);
            return $page->fresh();
        });
    }

    public function reorderPages(WebsiteSite $site,array $pageIds): void
    {
        $pages=$site->pages()->get()->keyBy('id');
        if(count($pageIds)!==$pages->count() || count(array_unique($pageIds))!==$pages->count() || count(array_intersect(array_map('intval',$pageIds),$pages->keys()->all()))!==$pages->count())
            throw ValidationException::withMessages(['pages'=>'Page order is invalid.']);
        DB::transaction(function() use($pageIds,$pages){ foreach(array_values($pageIds) as $i=>$id) $pages[(int)$id]->update(['sort_order'=>$i]); });
    }

    public function publish(WebsiteSite $site): WebsiteSite
    {
        return DB::transaction(function() use($site){
            $site->load(['pages'=>fn($q)=>$q->orderBy('sort_order')]);
            foreach($site->pages as $page) $page->update(['status'=>'published']);
            $primary=$site->domains()->where('status','verified')->where('primary',true)->first() ?? $site->domains()->where('status','verified')->first();
            $site->update([
                'status'=>'published','published_at'=>now(),
                'active_domain'=>$primary?->domain,
                'published_revision_id'=>optional($site->revisions()->latest('id')->first())->id
            ]);
            return $site->fresh('pages','domains');
        });
    }

    public function addDomain(WebsiteSite $site,string $domain): WebsiteDomain
    {
        $domain=strtolower(trim($domain)); $domain=preg_replace('/^https?:\/\//','',$domain); $domain=rtrim($domain,'/');
        if(!filter_var($domain,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) throw ValidationException::withMessages(['domain'=>'Enter a valid domain name.']);
        $existing=WebsiteDomain::where('domain',$domain)->first();
        if($existing && $existing->website_site_id!==$site->id) throw ValidationException::withMessages(['domain'=>'This domain is already registered.']);
        return $existing??WebsiteDomain::create(['website_site_id'=>$site->id,'domain'=>$domain,'type'=>'custom','status'=>'pending','verification_method'=>'dns_txt','verification_token'=>Str::random(40)]);
    }

    public function setSubdomain(WebsiteSite $site, string $subdomain): WebsiteSite
    {
        $subdomain = strtolower(trim($subdomain));
        $subdomain = preg_replace('/^https?:\\/\\//', '', $subdomain);
        $subdomain = rtrim((string) $subdomain, '/');
        if (!filter_var($subdomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw ValidationException::withMessages(['subdomain' => 'Enter a valid hostname, for example shop.example.com.']);
        }
        if (WebsiteSite::where('subdomain', $subdomain)->where('id', '!=', $site->id)->exists()
            || WebsiteSite::where('active_domain', $subdomain)->where('id', '!=', $site->id)->exists()
            || WebsiteDomain::where('domain', $subdomain)->where('website_site_id', '!=', $site->id)->exists()) {
            throw ValidationException::withMessages(['subdomain' => 'This hostname is already assigned to another website or domain.']);
        }
        $site->update(['subdomain' => $subdomain]);
        return $site->fresh(['pages', 'domains']);
    }

    public function setPrimaryDomain(WebsiteSite $site, WebsiteDomain $domain): WebsiteDomain
    {
        if ((int) $domain->website_site_id !== (int) $site->id) {
            throw ValidationException::withMessages(['domain' => 'Domain does not belong to this website.']);
        }
        if ($domain->status !== 'verified') {
            throw ValidationException::withMessages(['domain' => 'Only verified domains can be primary.']);
        }
        return DB::transaction(function () use ($site, $domain) {
            $site->domains()->update(['primary' => false]);
            $domain->update(['primary' => true]);
            $site->update(['active_domain' => $domain->domain]);
            return $domain->fresh();
        });
    }

    public function verifyDomain(WebsiteDomain $domain,string $token): WebsiteDomain
    {
        if(!hash_equals((string)$domain->verification_token,trim($token))) throw ValidationException::withMessages(['token'=>'Domain verification token is invalid.']);
        $domain->update(['status'=>'verified','verified_at'=>now(),'verification_token'=>null]);
        return $domain->fresh();
    }

    public function normalizeContent(array $content): array
    {
        $sections=$content['sections']??[];
        if(!is_array($sections)) throw ValidationException::withMessages(['content'=>'Sections must be an array.']);
        $out=[];
        foreach($sections as $section){
            if(!is_array($section)) throw ValidationException::withMessages(['content'=>'Each section must be an object.']);
            $type=(string)($section['type']??'');
            if(!in_array($type,self::SECTION_TYPES,true)) throw ValidationException::withMessages(['content'=>'Unsupported section type: '.$type]);
            $id=(string)($section['id']??Str::uuid());
            if($id==='') $id=(string)Str::uuid();
            $data=is_array($section['data']??null)?$section['data']:[];
            $out[]=['id'=>$id,'type'=>$type,'data'=>$data];
        }
        return ['sections'=>$out];
    }

    public static function sectionTypes(): array { return self::SECTION_TYPES; }
}