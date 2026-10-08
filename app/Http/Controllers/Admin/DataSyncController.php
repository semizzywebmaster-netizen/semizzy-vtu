<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\DataSync\DataSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class DataSyncController extends Controller {
 public function index(DataSyncService $sync){return Inertia::render('Admin/DataSync',['datasets'=>$sync->datasets(),'history'=>\DB::table('data_sync_runs')->latest('id')->limit(30)->get()]);}
 public function sync(Request $request,DataSyncService $service){$key=(string)$request->input('dataset_key'); $result=$service->sync($key,$request->user()); return back()->with('success',$result['dataset_key'].' synchronization completed.');}
 public function syncAll(Request $request,DataSyncService $service){$results=$service->syncAll($request->user()); return back()->with('success','All configured reference datasets synchronized.');}
}