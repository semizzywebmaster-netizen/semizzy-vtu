<?php
namespace Semizzy\Addons\EventsEntertainment\Http\Controllers;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Inertia\Inertia;use Semizzy\Addons\EventsEntertainment\Models\EventTicketType;use Semizzy\Addons\EventsEntertainment\Services\EventTicketService;
final class EventCheckoutController extends Controller{
 public function create(Request $request,EventTicketType $ticketType){abort_unless($ticketType->is_active,404);$ticketType->load('event');abort_unless($ticketType->event->status==='published',404);return Inertia::render('Events/Checkout',['ticketType'=>$ticketType]);}
 public function store(Request $request,EventTicketType $ticketType,EventTicketService $service){$data=$request->validate(['quantity'=>'required|integer|min:1','attendee_name'=>'nullable|string|max:255','attendee_email'=>'nullable|email|max:255','attendee_phone'=>'nullable|string|max:40']);$order=$service->createPendingOrder($request->user()?->id,$ticketType,$data['quantity'],$data);return redirect('/events/orders/'.$order->id)->with('success','Order created. Continue to payment.');}
}
