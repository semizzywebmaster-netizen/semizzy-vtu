<?php
namespace Semizzy\Addons\TravelTickets\Http\Controllers;
use Illuminate\Http\Request;use App\Http\Controllers\Controller;use Semizzy\Addons\TravelTickets\Services\TravelSearchService;
class TravelSearchController extends Controller{
 public function search(Request $request,TravelSearchService $service){$d=$request->validate(['type'=>'required|in:flight,bus,hotel','payload'=>'required|array']);return response()->json($service->search($d['type'],$d['payload']));}
}