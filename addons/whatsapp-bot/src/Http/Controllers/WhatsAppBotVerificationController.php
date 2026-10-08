<?php
namespace Addons\WhatsAppBot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Security\OtpChallengeService;
use Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway;
use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppBotVerificationController extends Controller
{
 public function show(Request $request): Response {
  $user=$request->user();
  return Inertia::render('WhatsAppBot/Verify',[
   'phone'=>$user->phone,
   'verified'=>(bool)$user->whatsapp_verified_at,
   'transactionsEnabled'=>(bool)$user->whatsapp_transaction_enabled,
  ]);
 }

 public function send(Request $request, OtpChallengeService $otp): RedirectResponse {
  $user=$request->user();
  if(!$user->phone) return back()->withErrors(['whatsapp'=>'A registered phone number is required.']);
  $otp->sendToUser($user,'whatsapp_link','WhatsApp verification','whatsapp');
  return back()->with('success','A verification code has been sent to your registered WhatsApp number.');
 }

 public function verify(Request $request): RedirectResponse {
  $user=$request->user();
  $data=$request->validate(['phone'=>'required|string','otp_code'=>'required|digits:6']);
  $normalize=static function(string $phone): string {
   $phone=preg_replace('/[^0-9+]/','',$phone) ?? '';
   if(str_starts_with($phone,'0')) $phone='+234'.substr($phone,1);
   elseif(str_starts_with($phone,'234')) $phone='+'.$phone;
   return $phone;
  };
  $submittedPhone=$normalize($data['phone']);
  $currentPhone=$normalize((string)$user->phone);
  $challenge=OtpChallenge::query()->where('user_id',$user->id)->where('purpose','whatsapp_link')->whereNull('consumed_at')->latest()->first();
  if(!$challenge || $normalize((string)$challenge->destination)!==$currentPhone || $submittedPhone!==$currentPhone || $challenge->expires_at->isPast() || $challenge->attempts >= $challenge->max_attempts){
   throw ValidationException::withMessages(['phone'=>'The WhatsApp number does not match the number this OTP was sent to, or the OTP has expired.']);
  }
  if(!Hash::check((string)$data['otp_code'],$challenge->code_hash)){
   $challenge->increment('attempts');
   throw ValidationException::withMessages(['otp_code'=>'The WhatsApp verification code is incorrect.']);
  }
  $challenge->update(['consumed_at'=>now()]);
  $user->forceFill(['whatsapp_verified_at'=>now(),'whatsapp_transaction_enabled'=>true])->saveOrFail();
  return back()->with('success','WhatsApp number confirmed and verified. WhatsApp transactions are now enabled.');
 }}
