<?php
namespace Semizzy\Addons\Government\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Semizzy\Addons\Government\Models\GovernmentService;
use Semizzy\Addons\Government\Models\GovernmentApplication;
use Semizzy\Addons\Government\Models\GovernmentDocument;

class GovernmentServicesService {
 public function createApplication(int $userId,GovernmentService $service,array $data=[]): GovernmentApplication {
  if($service->status!=='active') throw new \RuntimeException('Government service is unavailable.');
  return DB::transaction(function()use($userId,$service,$data){
   return GovernmentApplication::create([
    'reference'=>'GOV-'.strtoupper(Str::random(12)),
    'user_id'=>$userId,'service_id'=>$service->id,'status'=>'draft',
    'amount'=>$service->price,'currency'=>$service->currency,
    'application_data'=>$data,'metadata'=>['fulfillment_mode'=>$service->fulfillment_mode],
   ]);
  });
 }

 public function addDocument(GovernmentApplication $application,array $fileData): GovernmentDocument {
  if(in_array($application->status,['completed','failed','cancelled'],true)){
   throw new \RuntimeException('This application no longer accepts documents.');
  }
  return DB::transaction(function()use($application,$fileData){
   return GovernmentDocument::create([
    'application_id'=>$application->id,
    'document_type'=>$fileData['document_type'],
    'disk'=>$fileData['disk'] ?? 'local',
    'path'=>$fileData['path'],
    'original_name'=>$fileData['original_name'] ?? null,
    'mime_type'=>$fileData['mime_type'] ?? null,
    'size_bytes'=>$fileData['size_bytes'] ?? null,
    'status'=>'pending',
    'metadata'=>$fileData['metadata'] ?? [],
   ]);
  });
 }

 public function submitApplication(GovernmentApplication $application,bool $requireDocumentReview=true): GovernmentApplication {
  if(!in_array($application->status,['draft','awaiting_documents','paid'],true)){
   throw new \RuntimeException('Application cannot be submitted from its current status.');
  }
  $documents=$application->documents()->get();
  if($documents->isEmpty() && $requireDocumentReview){
   $application->update(['status'=>'awaiting_documents']);
   return $application->fresh();
  }
  if($requireDocumentReview && $documents->contains(fn($d)=>$d->status!=='approved')){
   $application->update(['status'=>'awaiting_documents']);
   return $application->fresh();
  }
  $application->update(['status'=>'submitted','submitted_at'=>now()]);
  return $application->fresh();
 }
}