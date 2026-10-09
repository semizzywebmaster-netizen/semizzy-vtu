<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  $catalog = [
   ['cac_business_name','CAC Business Name Registration','Corporate Affairs Commission','manual','active',[
    ['key'=>'proposed_name','label'=>'Proposed business name','type'=>'text','required'=>true],
    ['key'=>'email','label'=>'Email address','type'=>'email','required'=>true],
    ['key'=>'business_address','label'=>'Business address','type'=>'textarea','required'=>true],
    ['key'=>'date_of_commencement','label'=>'Date of commencement','type'=>'date','required'=>true],
    ['key'=>'proprietor_identification','label'=>'Means of identification','type'=>'file','required'=>true,'accept'=>'image/*,application/pdf'],
    ['key'=>'passport_photo','label'=>'Passport photograph','type'=>'file','required'=>true,'accept'=>'image/*'],
    ['key'=>'signature','label'=>'Signature','type'=>'file','required'=>true,'accept'=>'image/*']
   ]],
   ['cac_company_registration','CAC Company Registration','Corporate Affairs Commission','manual','active',[
    ['key'=>'proposed_name','label'=>'Proposed company name','type'=>'text','required'=>true],
    ['key'=>'company_type','label'=>'Company type','type'=>'select','required'=>true,'options'=>['private_limited','public_limited','limited_by_guarantee','unlimited']],
    ['key'=>'registered_address','label'=>'Registered office address','type'=>'textarea','required'=>true],
    ['key'=>'directors_details','label'=>'Directors details','type'=>'json','required'=>true],
    ['key'=>'shareholders_details','label'=>'Shareholders details','type'=>'json','required'=>true],
    ['key'=>'identification_documents','label'=>'Identification documents','type'=>'file','required'=>true,'accept'=>'image/*,application/pdf'],
    ['key'=>'passport_photographs','label'=>'Passport photographs','type'=>'file','required'=>true,'accept'=>'image/*']
   ]],
   ['cac_incorporated_trustees','CAC Incorporated Trustees Registration','Corporate Affairs Commission','manual','active',[
    ['key'=>'proposed_name','label'=>'Proposed organization name','type'=>'text','required'=>true],
    ['key'=>'objectives','label'=>'Objectives','type'=>'textarea','required'=>true],
    ['key'=>'registered_address','label'=>'Registered address','type'=>'textarea','required'=>true],
    ['key'=>'trustees_details','label'=>'Trustees details','type'=>'json','required'=>true],
    ['key'=>'identification_documents','label'=>'Trustees identification documents','type'=>'file','required'=>true,'accept'=>'image/*,application/pdf']
   ]],
   ['taxpayer_registration_tin','Taxpayer Registration / TIN','Nigeria Revenue Service','manual','active',[
    ['key'=>'taxpayer_category','label'=>'Taxpayer category','type'=>'select','required'=>true,'options'=>['individual','corporate','non_resident','free_trade_zone']],
    ['key'=>'nin_or_registration_number','label'=>'NIN or CAC registration number','type'=>'text','required'=>true],
    ['key'=>'supporting_document','label'=>'Supporting document','type'=>'file','required'=>false,'accept'=>'image/*,application/pdf']
   ]],
   ['scuml_registration','SCUML Registration','Special Control Unit against Money Laundering (EFCC)','manual','active',[
    ['key'=>'business_sector','label'=>'Business sector','type'=>'text','required'=>true],
    ['key'=>'cac_certificate','label'=>'CAC certificate','type'=>'file','required'=>true,'accept'=>'application/pdf,image/*'],
    ['key'=>'cac_status_report','label'=>'CAC status report / equivalent','type'=>'file','required'=>true,'accept'=>'application/pdf,image/*'],
    ['key'=>'tin_printout','label'=>'TIN printout','type'=>'file','required'=>true,'accept'=>'application/pdf,image/*'],
    ['key'=>'memorandum_articles','label'=>'Memorandum and Articles of Association','type'=>'file','required'=>false,'accept'=>'application/pdf']
   ]],
   ['birth_registration','Birth Registration / Birth Certificate','National Population Commission','manual','active',[
    ['key'=>'child_nin','label'=>'Child NIN','type'=>'text','required'=>true],
    ['key'=>'parent_attestation_number','label'=>'Parent attestation number','type'=>'text','required'=>true]
   ]],
   ['birth_attestation','Birth Attestation','National Population Commission','manual','active',[
    ['key'=>'nin','label'=>'NIN','type'=>'text','required'=>true],
    ['key'=>'court_affidavit','label'=>'Court sworn affidavit','type'=>'file','required'=>true,'accept'=>'application/pdf,image/*']
   ]],
   ['standard_passport','Standard International Passport Application','Nigeria Immigration Service','manual','active',[
    ['key'=>'nin_slip','label'=>'NIN slip','type'=>'file','required'=>true,'accept'=>'application/pdf,image/*'],
    ['key'=>'birth_or_age_declaration','label'=>'Birth certificate or declaration of age','type'=>'file','required'=>true,'accept'=>'application/pdf,image/*'],
    ['key'=>'local_government_certificate','label'=>'Local Government / indigene certificate','type'=>'file','required'=>true,'accept'=>'application/pdf,image/*'],
    ['key'=>'passport_photo','label'=>'ICAO-compliant passport photograph','type'=>'file','required'=>true,'accept'=>'image/*'],
    ['key'=>'current_passport','label'=>'Current passport for reissue','type'=>'file','required'=>false,'accept'=>'application/pdf,image/*']
   ]],
   ['drivers_license_new','New Driver’s Licence Registration','Federal Road Safety Corps','manual','active',[
    ['key'=>'identity','label'=>'Identity / NIN details','type'=>'text','required'=>true],
    ['key'=>'medical_certificate','label'=>'Medical certificate / required supporting record','type'=>'file','required'=>false,'accept'=>'application/pdf,image/*']
   ]],
   ['drivers_license_renewal','Driver’s Licence Renewal','Federal Road Safety Corps','manual','active',[
    ['key'=>'existing_license','label'=>'Existing driver’s licence details','type'=>'text','required'=>true],
    ['key'=>'identity','label'=>'NIN / identity details','type'=>'text','required'=>true]
   ]],
  ];
  foreach($catalog as [$key,$name,$agency,$mode,$status,$requirements]){
   if(!DB::table('government_services')->where('service_key',$key)->exists()){
    DB::table('government_services')->insert([
     'service_key'=>$key,'name'=>$name,'agency'=>$agency,'description'=>'Admin-configurable government registration or certificate service.',
     'fulfillment_mode'=>$mode,'provider_reference'=>null,'price'=>0,'currency'=>'NGN','status'=>$status,
     'requirements'=>json_encode($requirements),'metadata'=>json_encode(['source'=>'official-government-service-research','seed_version'=>1]),
     'created_at'=>now(),'updated_at'=>now()
    ]);
   }
  }
 }
 public function down(): void {
  DB::table('government_services')->whereIn('service_key',['cac_business_name','cac_company_registration','cac_incorporated_trustees','taxpayer_registration_tin','scuml_registration','birth_registration','birth_attestation','standard_passport','drivers_license_new','drivers_license_renewal'])->delete();
 }
};