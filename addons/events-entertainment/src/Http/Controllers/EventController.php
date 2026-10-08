<?php
namespace Semizzy\Addons\EventsEntertainment\Http\Controllers;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Inertia\Inertia;use Semizzy\Addons\EventsEntertainment\Models\Event;
final class EventController extends Controller {
 public function index(Request $request){
  $events=Event::with(['organizer','venue'])->where('status','published')->where('starts_at','>=',now()->subDay())
   ->when($request->filled('category'),fn($q)=>$q->where('category',$request->string('category')->toString()))
   ->when($request->filled('event_type'),fn($q)=>$q->where('event_type',$request->string('event_type')->toString()))
   ->when($request->filled('city'),fn($q)=>$q->whereHas('venue',fn($v)=>$v->where('city','like','%'.$request->string('city')->toString().'%')))
   ->when($request->filled('q'),fn($q)=>$q->where(fn($x)=>$x->where('title','like','%'.$request->string('q')->toString().'%')->orWhere('summary','like','%'.$request->string('q')->toString().'%')))
   ->orderBy('starts_at')->paginate(24)->withQueryString();
  return Inertia::render('Events/Index',['events'=>$events,'categories'=>config('events-entertainment.categories',[]),'filters'=>$request->only(['q','category','event_type','city'])]);
 }
 public function show(Event $event){abort_unless($event->status==='published',404);$event->load(['organizer','venue','occurrences.venue','ticketTypes'=>fn($q)=>$q->where('is_active',true)]);return Inertia::render('Events/Show',['event'=>$event]);}
}
