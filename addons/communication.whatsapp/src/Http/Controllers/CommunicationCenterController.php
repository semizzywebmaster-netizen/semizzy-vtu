<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;

use App\Models\Communication\Conversation;
use App\Models\Communication\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommunicationCenterController
{
 public function conversations(Request $request): JsonResponse
 {
  $q=Conversation::query()->with(['user:id,name,phone'])->withCount('messages')->orderByDesc('last_message_at')->orderByDesc('id');
  if($request->filled('channel')) $q->where('channel',$request->string('channel'));
  if($request->filled('status')) $q->where('status',$request->string('status'));
  if($request->filled('search')) $q->where(function($x)use($request){$s=$request->string('search');$x->where('external_contact','like',"%$s%")->orWhereHas('user',fn($u)=>$u->where('name','like',"%$s%")->orWhere('phone','like',"%$s%"));});
  return response()->json($q->paginate(min(max((int)$request->input('per_page',25),1),100)));
 }

 public function show(Conversation $conversation): JsonResponse
 {
  return response()->json($conversation->load(['user:id,name,phone','messages'=>fn($q)=>$q->with('attempts')->orderBy('id')]));
 }

 public function reply(Request $request,Conversation $conversation): JsonResponse
 {
  $data=$request->validate(['body'=>'required|string|max:10000','idempotency_key'=>'required|string|max:191']);
  $message=Message::firstOrCreate(
   ['channel'=>$conversation->channel,'idempotency_key'=>$data['idempotency_key']],
   ['conversation_id'=>$conversation->id,'user_id'=>$conversation->user_id,'channel'=>$conversation->channel,'direction'=>'outbound','recipient'=>$conversation->external_contact,'body'=>$data['body'],'status'=>'queued']
  );
  return response()->json(['message'=>$message],202);
 }
}
