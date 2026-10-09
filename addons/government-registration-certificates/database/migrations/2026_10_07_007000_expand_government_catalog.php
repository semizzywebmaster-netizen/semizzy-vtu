<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  $catalog=[
   ['cac_name_reservation','CAC Name Reservation','Corporate Affairs Commission'],
   ['cac_change_business_name','CAC Change of Business Name','Corporate Affairs Commission'],
   ['cac_business_name_post_registration','CAC Business Name Post-Registration Filing','Corporate Affairs Commission'],
   ['cac_annual_return','CAC Annual Return Filing','Corporate Affairs Commission'],
   ['cac_status_report','CAC Status Report / Company Search','Corporate Affairs Commission'],
   ['cac_company_certified_copy','CAC Certified True Copy / Certified Extract','Corporate Affairs Commission'],
   ['cac_director_change','CAC Change of Directors / Particulars','Corporate Affairs Commission'],
   ['cac_registered_address_change','CAC Change of Registered Address','Corporate Affairs Commission'],
   ['cac_share_capital_change','CAC Share Capital / Allotment Filing','Corporate Affairs Commission'],
   ['cac_company_reissue','CAC Company Reissue / Replacement Documents','Corporate Affairs Commission'],
   ['passport_reissue','International Passport Reissue','Nigeria Immigration Service'],
   ['passport_change_of_data','International Passport Change of Data','Nigeria Immigration Service'],
   ['passport_lost_damaged','Lost / Damaged Passport Replacement','Nigeria Immigration Service'],
   ['passport_official','Official Passport Application','Nigeria Immigration Service'],
   ['scuml_registration','SCUML Registration and Certificate','Special Control Unit against Money Laundering'],
   ['scuml_update','SCUML Profile / Registration Update','Special Control Unit against Money Laundering'],
   ['tin_registration','Tax Identification Number Registration','Nigeria Revenue Service'],
   ['tin_certificate','Tax Identification / Tax Clearance Certificate Request','Nigeria Revenue Service'],
   ['birth_certificate','Birth Certificate / Birth Registration','National Population Commission'],
   ['birth_attestation','Birth Attestation','National Population Commission'],
   ['marriage_certificate','Marriage Certificate / Certified Copy Request','National Population Commission'],
   ['drivers_license_new','Driver Licence New Application','Federal Road Safety Corps'],
   ['drivers_license_renewal','Driver Licence Renewal','Federal Road Safety Corps'],
   ['drivers_license_replacement','Driver Licence Replacement','Federal Road Safety Corps'],
   ['drivers_license_change_data','Driver Licence Change of Data','Federal Road Safety Corps'],
   ['vehicle_registration','Vehicle Registration / Documentation','Federal Road Safety Corps'],
   ['vehicle_license_renewal','Vehicle Licence Renewal','Federal Road Safety Corps'],
   ['roadworthiness_certificate','Certificate of Roadworthiness','Federal Road Safety Corps'],
   ['international_driving_permit','International Driving Permit','Federal Road Safety Corps'],
   ['police_clearance','Police Character / Clearance Certificate','Nigeria Police Force'],
   ['police_extract','Police Extract / Incident Documentation','Nigeria Police Force'],
   ['exporter_registration','Exporter Registration / Documentation','Nigerian Export Promotion Council'],
   ['trademark_registration','Trademark Registration','Trademarks Registry / Federal Ministry of Industry, Trade and Investment'],
   ['patent_registration','Patent Registration','Federal Ministry of Industry, Trade and Investment'],
   ['design_registration','Industrial Design Registration','Federal Ministry of Industry, Trade and Investment'],
   ['nepc_export_certificate','NEPC Export Certificate / Related Service','Nigerian Export Promotion Council'],
   ['nafdac_registration','NAFDAC Product / Premises Registration','NAFDAC'],
   ['son_product_registration','Standards / Product Certification Service','Standards Organisation of Nigeria'],
   ['nigerian_citizenship_certificate','Nigerian Citizenship Certificate / Related Application','Nigeria Immigration Service'],
   ['residence_permit','Residence Permit / Related Immigration Service','Nigeria Immigration Service']
  ];
  foreach($catalog as [$key,$name,$agency]){
   if(!DB::table('government_services')->where('service_key',$key)->exists()){
    $requirements=[
      ['key'=>'applicant_name','label'=>'Applicant / business name','type'=>'text','required'=>true],
      ['key'=>'phone','label'=>'Phone number','type'=>'text','required'=>true],
      ['key'=>'email','label'=>'Email address','type'=>'email','required'=>true],
      ['key'=>'identification','label'=>'Identification / supporting document','type'=>'file','required'=>true,'accept'=>'application/pdf,image/*']
    ];
    DB::table('government_services')->insert([
      'service_key'=>$key,'name'=>$name,'agency'=>$agency,
      'description'=>'Admin-configurable government service. Requirements and pricing must be verified and maintained by Admin against the current issuing authority.',
      'fulfillment_mode'=>'api_or_manual','provider_reference'=>null,'price'=>0,'currency'=>'NGN','status'=>'inactive',
      'requirements'=>json_encode($requirements),'metadata'=>json_encode(['source'=>'government-catalog-expansion','requires_admin_verification'=>true]),
      'created_at'=>now(),'updated_at'=>now()
    ]);
   }
  }
 }
 public function down(): void {}
};