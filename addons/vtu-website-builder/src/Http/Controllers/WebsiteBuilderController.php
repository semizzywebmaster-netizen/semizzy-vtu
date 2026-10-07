<?php
namespace Addons\\VtuWebsiteBuilder\\Http\\Controllers;
use Addons\\VtuWebsiteBuilder\\Models\\{WebsiteSite,WebsitePage};
use Addons\\VtuWebsiteBuilder\\Services\\WebsiteBuilderService;
use Illuminate\\Http\\Request;
use Illuminate\\Routing\\Controller;
class WebsiteBuilderController extends Controller {
 public function __construct(private WebsiteBuilderService $service){}
 public function index(Request $r){return inertia('WebsiteBuilder',['sites'=>WebsiteSite::where('user_id',$r->user()->id)->with('pages','domains')->latest()->get()]);}
 public function store(Request $r){$data=$r->validate(['name'=>'required|string|max:120','slug'=>'nullable|string|max:120','template_key'=>'nullable|string|max:80']);$this->service->createSite($r->user()->id,$data);return back()->with('success','Website created.');}
 public function pageEditor(Request $r,WebsiteSite $site,WebsitePage $page){abort_unless($site->user_id===$r->user()->id && $page->website_site_id===$site->id,404);return inertia('WebsitePageEditor',['site'=>$site->load('domains'),'page'=>$page]);}
 public function page(Request $r,WebsiteSite $site,WebsitePage $page){abort_unless($site->user_id===$r->user()->id && $page->website_site_id===$site->id,404);return response()->json($page);}
 public function savePage(Request $r,WebsiteSite $site,WebsitePage $page){abort_unless($site->user_id===$r->user()->id && $page->website_site_id===$site->id,404);$data=$r->validate(['title'=>'nullable|string|max:160','content'=>'nullable|array','seo'=>'nullable|array']);$this->service->savePage($site,$page,$data,$r->user()->id);return back()->with('success','Page saved.');}
 public function publish(Request $r,WebsiteSite $site){abort_unless($site->user_id===$r->user()->id,404);$this->service->publish($site);return back()->with('success','Website published.');}
 public function domain(Request $r,WebsiteSite $site){abort_unless($site->user_id===$r->user()->id,404);$data=$r->validate(['domain'=>'required|string|max:253']);$this->service->addDomain($site,$data['domain']);return back()->with('success','Domain added; verification is pending.');}
}