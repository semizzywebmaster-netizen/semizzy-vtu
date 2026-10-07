<?php
namespace Semizzy\Addons\TravelTickets\Http\Controllers;
use Illuminate\Http\Request;use Inertia\Inertia;use App\Http\Controllers\Controller;use Semizzy\Addons\TravelTickets\Models\TravelService;use Semizzy\Addons\TravelTickets\Models\TravelBooking;use Semizzy\Addons\TravelTickets\Services\TravelBookingService;
class TravelTicketsController extends Controller{
 public function index(){return Inertia::render('TravelTickets/Index',['services'=>TravelService::where('enabled',true)->orderBy('type')->get()]);}
 public function apiServices(){return response()->json(['services'=>TravelService::where('enabled',true)->orderBy('type')->get()]);}
 public function bookings(Request $request){return Inertia::render('TravelTickets/Bookings',['bookings'=>TravelBooking::where('user_id',$request->user()->id)->latest()->paginate(20)]);}
 public function store(Request $request,TravelBookingService $service){$d=$request->validate(['service_id'=>'required|integer','type'=>'required|string|in:flight,bus,hotel','amount'=>'required|numeric|min:0','fee'=>'nullable|numeric|min:0','currency'=>'nullable|string|max:10','passengers'=>'nullable|array','search_data'=>'nullable|array','booking_data'=>'nullable|array','idempotency_key'=>'required|string|max:100']);return response()->json(['booking'=>$service->createBooking($request->user()->id,(int)$d['service_id'],$d['type'],$d,$d['idempotency_key'])],201);}
}