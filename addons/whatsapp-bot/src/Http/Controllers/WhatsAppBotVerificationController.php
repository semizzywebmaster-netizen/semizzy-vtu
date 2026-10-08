<?php
namespace Addons\WhatsAppBot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OtpChallenge;
use App\Models\User;
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

 public function send(Request $request, CommunicationProviderGateway $gateway): RedirectResponse {
  $user=$request->user();
  if(!$user->phone) return back()->withErrors(['whatsapp'=>'A registered phone number is required.']);
  $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
  OtpChallenge::query()->where('user_id',$user->id)->where('purpose','whatsapp_link')->whereNull('consumed_at')->update(['consumed_at'=>now()]);
  $challenge=OtpChallenge::create(['user_id'=>$user->id,'channel'=>'whatsapp','purpose'=>'whatsapp_link','destination'=>$user->phone,'code_hash'=>Hash::make($code),'expires_at'=>now()->addMinutes(10),'attempts'=>0,'max_attempts'=>5,'ip_address'=>$request->ip()]);
  $conversation=Conversation::firstOrCreate(['channel'=>'whatsapp','external_contact'=>$user->phone],['user_id'=>$user->id,'status'=>'open']);
  $message=Message::create(['conversation_id'=>$conversation->id,'user_id'=>$user->id,'channel'=>'whatsapp','direction'=>'outbound','recipient'=>$user->phone,'body'=>"Your SEMIZZY ONE WhatsApp verification code is {$code}. It expires in 10 minutes.",'status'=>'queued','idempotency_key'=>'wa-link:'.$user->id.':'.bin2hex(random_bytes(8)),'metadata'=>['purpose'=>'whatsapp_link']]);
  $gateway->send($message);
  return back()->with('success','A verification code has been sent to your registered WhatsApp number.');
 }

 public function verify(Request $request): RedirectResponse {
  $user=$request->user();
  $request->validate(['otp_code'=>'required|digits:6']);
  $challenge=OtpChallenge::query()->where('user_id',$user->id)->where('purpose','whatsapp_link')->whereNull('consumed_at')->latest()->first();
  if(!$challenge || $challenge->expires_at->isPast() || $challenge->attempts >= $challenge->max_attempts || !Hash::check((string)$request->input('otp_code'),$challenge->code_hash)){
   if($challenge && !$challenge->expires_at->isPast()) $challenge->increment('attempts');
   throw ValidationException::withMessages(['otp_code'=>'Invalid or expired WhatsApp verification code.']);
  }
  $challenge->update(['consumed_at'=>now()]);
  $user->forceFill(['whatsapp_verified_at'=>now(),'whatsapp_transaction_enabled'=>true])->saveOrFail();
  return back()->with('success','WhatsApp is verified and enabled for WhatsApp transactions.');
 }
}
