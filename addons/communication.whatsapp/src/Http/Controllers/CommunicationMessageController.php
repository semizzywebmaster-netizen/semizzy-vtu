<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;

use Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway;
use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CommunicationMessageController
{
 public function retry(Request $request, Message $message, CommunicationProviderGateway $gateway): JsonResponse
 {
  if($message->status!=='failed') return response()->json(['message'=>'Only failed messages can be retried.'],422);
  $message->update(['status'=>'queued','failed_at'=>null]);
  try { $attempt=$gateway->send($message); return response()->json(['message'=>$message->fresh(),'attempt'=>$attempt],202); }
  catch(\Throwable $e) { return response()->json(['message'=>$message->fresh(),'error'=>'All configured providers failed.'],502); }
 }

 public function sendWhatsApp(Request $request,CommunicationProviderGateway $gateway): JsonResponse
 {
  $data=$request->validate([
   'user_id'=>'nullable|integer|exists:users,id',
   'recipient'=>'nullable|string|max:191',
   'body'=>'required|string|max:10000',
   'idempotency_key'=>'required|string|max:191',
  ]);
  $recipient=$data['recipient'] ?? null;
  $user=$data['user_id'] ? User::find($data['user_id']) : null;
  if(!$recipient && $user) $recipient=$user->phone;
  if(!$recipient) return response()->json(['message'=>'A recipient is required.'],422);

  $existing=Message::where('channel','whatsapp')->where('idempotency_key',$data['idempotency_key'])->first();
  if($existing) return response()->json(['message'=>$existing],200);

  $message=DB::transaction(function() use($user,$recipient,$data){
   $conversation=Conversation::firstOrCreate(
    ['channel'=>'whatsapp','external_contact'=>$recipient],
    ['user_id'=>$user?->id,'status'=>'open']
   );
   return Message::create([
    'conversation_id'=>$conversation->id,'user_id'=>$user?->id,'channel'=>'whatsapp',
    'direction'=>'outbound','recipient'=>$recipient,'body'=>$data['body'],
    'status'=>'queued','idempotency_key'=>$data['idempotency_key'],
   ]);
  });
  $attempt=$gateway->send($message);
  return response()->json(['message'=>$message->fresh(),'attempt'=>$attempt],202);
 }
}
