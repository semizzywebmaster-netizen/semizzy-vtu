<?php
namespace Semizzy\Addons\EventsEntertainment\Http\Controllers;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Inertia\Inertia;use Semizzy\Addons\EventsEntertainment\Models\EventOrder;
final class EventOrderController extends Controller{public function show(Request $request,EventOrder $order){abort_unless($order->user_id===$request->user()->id,403);$order->load(['event','items.ticketType','items.tickets']);return Inertia::render('Events/Order',['order'=>$order]);}}
