<?php
namespace Semizzy\Addons\EventsEntertainment\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Semizzy\Addons\EventsEntertainment\Models\EventTicket;

final class EventTicketController extends Controller
{
 public function show(Request $request, EventTicket $ticket)
 {
  abort_unless($ticket->user_id === $request->user()->id, 403);
  $ticket->load(['event','ticketType','occurrence.venue']);
  return \Inertia\Inertia::render('Events/Ticket', ['ticket'=>$ticket]);
 }

 public function qrPayload(Request $request, EventTicket $ticket)
 {
  abort_unless($ticket->user_id === $request->user()->id,403);
  abort_unless($ticket->status === 'issued',422);
  return response()->json(['ticket_number'=>$ticket->ticket_number,'payload'=>$ticket->ticket_number.'.'.hash_hmac('sha256',$ticket->ticket_number,config('app.key')),'status'=>$ticket->status,'event_id'=>$ticket->event_id,'occurrence_id'=>$ticket->occurrence_id]);
 }

 public function checkIn(Request $request, EventTicket $ticket)
 {
  $data=$request->validate(['payload'=>'required|string']);
  $ticket->load(['event','ticketType','occurrence']);
  abort_unless($request->user()->can('events.checkin.manage'),403);
  abort_unless($ticket->event && $ticket->event->status==='published',422,'Event is not active for check-in.');
  abort_unless($ticket->status==='issued',422,'Ticket is not check-in eligible.');
  [$number,$signature]=array_pad(explode('.',$data['payload'],2),2,null);
  abort_unless($number===$ticket->ticket_number && $signature && hash_equals(hash_hmac('sha256',$ticket->ticket_number,config('app.key')),$signature),422,'Invalid ticket QR payload.');
  abort_if($ticket->checked_in_at!==null,409,'Ticket has already been checked in.');
  if($ticket->occurrence){abort_unless(in_array($ticket->occurrence->status,['scheduled'],true),422,'This occurrence is not open for check-in.');}
  $ticket->update(['status'=>'checked_in','checked_in_at'=>now(),'checked_in_by'=>$request->user()->id]);
  return response()->json(['ok'=>true,'message'=>'Ticket checked in successfully.','ticket'=>$ticket->fresh(['event','ticketType','occurrence'])]);
 }
}
