<?php
namespace Semizzy\Addons\BulkSms\Http\Controllers;
use App\Http\Controllers\Controller; use Illuminate\Http\Request; use Inertia\Inertia; use Semizzy\Addons\BulkSms\Models\BulkSmsCampaign; use Semizzy\Addons\BulkSms\Models\BulkSmsProduct; use Semizzy\Addons\BulkSms\Models\BulkSmsSenderId;
final class AdminBulkSmsController extends Controller {
 public function index(Request $r){return Inertia::render('Admin/BulkSms',['products'=>BulkSmsProduct::with('provider')->withCount('campaigns')->latest()->get(),'campaigns'=>BulkSmsCampaign::with('product')->latest()->paginate(25),'sender_ids'=>BulkSmsSenderId::latest()->paginate(25)]);}
}
