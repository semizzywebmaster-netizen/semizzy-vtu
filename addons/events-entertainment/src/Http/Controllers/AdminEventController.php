<?php
namespace Semizzy\Addons\EventsEntertainment\Http\Controllers;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Illuminate\Support\Str;use Inertia\Inertia;use Semizzy\Addons\EventsEntertainment\Models\{Event,EventOrganizer,EventVenue,EventTicketType};
final class AdminEventController extends Controller {
 public function index(){return Inertia::render('Admin/Events/Index',['events'=>Event::with(['organizer','venue'])->latest()->paginate(30)]);}
 public function store(Request $request){
  $data=$request->validate(['title'=>'required|string|max:255','category'=>'required|string|max:80','summary'=>'nullable|string|max:1000','description'=>'nullable|string','event_type'=>'required|in:physical,virtual,hybrid','starts_at'=>'required|date','ends_at'=>'nullable|date|after:starts_at','venue_id'=>'nullable|exists:event_venues,id','virtual_url'=>'nullable|url|max:2048']);
  $user=$request->user();$org=EventOrganizer::firstOrCreate(['user_id'=>$user->id],['display_name'=>$user->name??$user->email??'Organizer','slug'=>Str::slug(($user->name??'organizer').'-'.$user->id)]);
  Event::create($data+['organizer_id'=>$org->id,'slug'=>Str::slug($data['title']).'-'.Str::lower(Str::random(6)),'status'=>'draft','currency'=>'NGN','timezone'=>'Africa/Lagos']);
  return back()->with('success','Event draft created.');
 }
 public function venue(Request $request){$data=$request->validate(['name'=>'required|string|max:255','address'=>'nullable|string|max:500','city'=>'nullable|string|max:120','state'=>'nullable|string|max:120','country'=>'nullable|string|max:120','capacity'=>'nullable|integer|min:1']);EventVenue::create($data+['slug'=>Str::slug($data['name']).'-'.Str::lower(Str::random(6))]);return back()->with('success','Venue created.');}
 public function ticket(Request $request,Event $event){$data=$request->validate(['name'=>'required|string|max:120','description'=>'nullable|string','ticket_mode'=>'required|in:single,group','access_type'=>'required|in:paid,free,invite_only','price'=>'nullable|numeric|min:0','quantity'=>'nullable|integer|min:1','per_order_min'=>'nullable|integer|min:1','per_order_max'=>'nullable|integer|min:1','sales_start_at'=>'nullable|date','sales_end_at'=>'nullable|date|after_or_equal:sales_start_at']);if($data['access_type']==='free')$data['price']=0;EventTicketType::create($data+['event_id'=>$event->id,'is_active'=>true]);return back()->with('success','Ticket type created.');}
}
