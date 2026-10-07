<?php
namespace Semizzy\Addons\TravelTickets\Http\Controllers\Admin;
use Illuminate\Http\Request;use Inertia\Inertia;use App\Http\Controllers\Controller;use Semizzy\Addons\TravelTickets\Models\TravelService;use Semizzy\Addons\TravelTickets\Models\TravelBooking;
class TravelTicketsController extends Controller{
 public function index(){return Inertia::render('Admin/TravelTickets',['services'=>TravelService::orderBy('type')->get(),'bookings'=>TravelBooking::latest()->paginate(30)]);}
 public function storeService(Request $request){$d=$request->validate(['type'=>'required|in:flight,bus,hotel','name'=>'required|string|max:150','code'=>'required|string|max:100|unique:travel_services,code','description'=>'nullable|string','enabled'=>'boolean','requirements'=>'nullable|array']);TravelService::create($d);return back()->with('success','Travel service created.');}
 public function toggleService(TravelService $service){$service->update(['enabled'=>!$service->enabled]);return back()->with('success','Travel service status updated.');}
}