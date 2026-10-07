<?php
namespace Addons\VtuWebsiteBuilder\Http\Controllers;

use Addons\VtuWebsiteBuilder\Models\{WebsiteSite,WebsitePage,WebsiteDomain};
use Addons\VtuWebsiteBuilder\Services\WebsiteBuilderService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebsiteBuilderController extends Controller
{
 public function __construct(private WebsiteBuilderService $service){}
 private function own(Request $r,WebsiteSite $site,?WebsitePage $page=null): void { abort_unless((int)$site->user_id===(int)$r->user()->id && (!$page || (int)$page->website_site_id===(int)$site->id),404); }
 public function index(Request $r){return inertia('WebsiteBuilder',['sites'=>WebsiteSite::where('user_id',$r->user()->id)->with(['pages'=>fn($q)=>$q->orderBy('sort_order'),'domains'])->latest()->get()]);}
 public function store(Request $r){$data=$r->validate(['name'=>'required|string|max:120','slug'=>'nullable|string|max:120','template_key'=>'nullable|string|max:80']);$this->service->createSite($r->user()->id,$data);return back()->with('success','Website created.');}
 public function pageEditor(Request $r,WebsiteSite $site,WebsitePage $page){$this->own($r,$site,$page);return inertia('WebsitePageEditor',['site'=>$site->load('domains'),'page'=>$page,'sectionTypes'=>WebsiteBuilderService::sectionTypes()]);}
 public function page(Request $r,WebsiteSite $site,WebsitePage $page){$this->own($r,$site,$page);return response()->json($page);}
 public function savePage(Request $r,WebsiteSite $site,WebsitePage $page){$this->own($r,$site,$page);$data=$r->validate(['title'=>'nullable|string|max:160','content'=>'required|array','seo'=>'nullable|array']);$this->service->savePage($site,$page,$data,$r->user()->id);return back()->with('success','Page saved.');}
 public function addPage(Request $r,WebsiteSite $site){$this->own($r,$site);$data=$r->validate(['title'=>'required|string|max:160','slug'=>'nullable|string|max:160']);$page=$this->service->createPage($site,$data);return back()->with('success','Page created.');}
 public function deletePage(Request $r,WebsiteSite $site,WebsitePage $page){$this->own($r,$site,$page);$this->service->deletePage($site,$page);return redirect()->route('website-builder.index')->with('success','Page deleted.');}
 public function setHome(Request $r,WebsiteSite $site,WebsitePage $page){$this->own($r,$site,$page);$this->service->setHomePage($site,$page);return back()->with('success','Home page updated.');}
 public function reorderPages(Request $r,WebsiteSite $site){$this->own($r,$site);$data=$r->validate(['pages'=>'required|array','pages.*'=>'integer']);$this->service->reorderPages($site,$data['pages']);return back()->with('success','Page order saved.');}
 public function addSection(Request $r,WebsiteSite $site,WebsitePage $page){$this->own($r,$site,$page);$data=$r->validate(['type'=>'required|string','data'=>'nullable|array']);$this->service->addSection($page,$data['type'],$data['data']??[]);return back()->with('success','Section added.');}
 public function updateSection(Request $r,WebsiteSite $site,WebsitePage $page,string $section){$this->own($r,$site,$page);$data=$r->validate(['type'=>'required|string','data'=>'nullable|array']);$this->service->updateSection($page,$section,$data['type'],$data['data']??[]);return back()->with('success','Section updated.');}
 public function deleteSection(Request $r,WebsiteSite $site,WebsitePage $page,string $section){$this->own($r,$site,$page);$this->service->deleteSection($page,$section);return back()->with('success','Section removed.');}
 public function reorderSections(Request $r,WebsiteSite $site,WebsitePage $page){$this->own($r,$site,$page);$data=$r->validate(['sections'=>'required|array','sections.*'=>'string']);$this->service->reorderSections($page,$data['sections']);return back()->with('success','Section order saved.');}
 public function publish(Request $r,WebsiteSite $site){$this->own($r,$site);$this->service->publish($site);return back()->with('success','Website published.');}
 public function domain(Request $r,WebsiteSite $site){$this->own($r,$site);$data=$r->validate(['domain'=>'required|string|max:253']);$this->service->addDomain($site,$data['domain']);return back()->with('success','Domain added; verification is pending.');}
 public function verifyDomain(Request $r,WebsiteSite $site,WebsiteDomain $domain){$this->own($r,$site);abort_unless((int)$domain->website_site_id===(int)$site->id,404);$data=$r->validate(['token'=>'required|string|max:100']);$this->service->verifyDomain($domain,$data['token']);return back()->with('success','Domain verified.');}
}