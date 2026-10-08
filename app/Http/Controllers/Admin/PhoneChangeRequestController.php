<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\PhoneChangeRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class PhoneChangeRequestController extends Controller {
 public function index(){return inertia('Admin/PhoneChangeRequests',['requests'=>PhoneChangeRequest::with(['user:id,name,username,phone','reviewer:id,name'])->latest()->paginate(30)]);}
 public function screenshot(PhoneChangeRequest $change){abort_unless($change->screenshot_path,404); abort_unless(Storage::exists($change->screenshot_path),404); return response()->file(Storage::path($change->screenshot_path));}
 public function approve(Request $request,PhoneChangeRequest $change,AuditLogger $audit): RedirectResponse {
  $data=$request->validate(['admin_reason'=>'required|string|min:3|max:2000']);
  if($change->status!=='pending')return back()->withErrors(['request'=>'This request has already been reviewed.']);
  if(\App\Models\User::where('phone',$change->requested_phone)->where('id','!=',$change->user_id)->exists())return back()->withErrors(['request'=>'The requested phone number is already in use.']);
  $user=$change->user; $user->forceFill(['phone'=>$change->requested_phone,'phone_verified_at'=>null,'whatsapp_verified_at'=>null,'whatsapp_transaction_enabled'=>false])->saveOrFail();
  $change->update(['status'=>'approved','admin_reason'=>$data['admin_reason'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);
  $audit->record('admin.user.phone_change.approved',$user,['request_id'=>$change->id,'reason'=>$data['admin_reason']]);
  return back()->with('success','Phone-number change approved. The user must verify the new WhatsApp number before WhatsApp transactions are enabled.');
 }
 public function decline(Request $request,PhoneChangeRequest $change,AuditLogger $audit): RedirectResponse {
  $data=$request->validate(['admin_reason'=>'required|string|min:3|max:2000']);
  if($change->status!=='pending')return back()->withErrors(['request'=>'This request has already been reviewed.']);
  $change->update(['status'=>'declined','admin_reason'=>$data['admin_reason'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);
  $audit->record('admin.user.phone_change.declined',$change->user,['request_id'=>$change->id,'reason'=>$data['admin_reason']]);
  return back()->with('success','Phone-number change request declined.');
 }
}