<?php
namespace Addons\CommunicationWhatsapp\Http\Controllers;

use Addons\CommunicationWhatsapp\Services\CommunicationConsentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Communication\Consent;

class CommunicationConsentController
{
 public function set(Request $request,CommunicationConsentService $service): JsonResponse
 {
  $data=$request->validate(['channel'=>'required|string|max:32','purpose'=>'nullable|string|max:64','opted_in'=>'required|boolean','source'=>'nullable|string|max:64']);
  $consent=$service->set($request->user()->id,$data['channel'],$data['purpose'] ?? 'marketing',$data['opted_in'],$data['source'] ?? 'user');
  return response()->json(['consent'=>$consent]);
 }
 public function mine(Request $request): JsonResponse
 {
  return response()->json(['consents'=>Consent::where('user_id',$request->user()->id)->get()]);
 }
}
