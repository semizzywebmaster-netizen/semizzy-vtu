<?php
namespace App\Http\Controllers;
use App\Models\PhoneChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
class PhoneChangeRequestController extends Controller {
 public function create(Request $request): Response { return Inertia::render('Account/PhoneChangeRequest',['currentPhone'=>$request->user()->phone,'pending'=>PhoneChangeRequest::where('user_id',$request->user()->id)->where('status','pending')->latest()->first()]); }
 public function store(Request $request): RedirectResponse {
  $user=$request->user();
  if(PhoneChangeRequest::where('user_id',$user->id)->where('status','pending')->exists()) return back()->withErrors(['phone'=>'You already have a pending phone-number change request.']);
  $phone=preg_replace('/[^0-9+]/','',(string)$request->input('phone'));
  if(str_starts_with($phone,'0'))$phone='+234'.substr($phone,1); elseif(str_starts_with($phone,'234'))$phone='+'.$phone;
  $data=$request->validate(['phone'=>['required','string','regex:/^\+234[789][0-9]{9}$/','unique:users,phone'],'reason'=>'required|string|min:10|max:2000','screenshot'=>'nullable|image|max:5120']);
  $path=$request->hasFile('screenshot')?$request->file('screenshot')->store('phone-change-requests'):null;
  PhoneChangeRequest::create(['user_id'=>$user->id,'current_phone'=>$user->phone,'requested_phone'=>$phone,'reason'=>$data['reason'],'screenshot_path'=>$path]);
  return back()->with('success','Your phone-number change request has been sent to Admin for review.');
 }
}