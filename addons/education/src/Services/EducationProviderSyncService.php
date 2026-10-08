<?php
namespace Semizzy\Addons\Education\Services;
use Semizzy\Addons\Education\Models\EducationProduct;
final class EducationProviderSyncService {
 public function syncInstitutions(array $items): int {
  return app(EducationInstitutionImportService::class)->import($items,'provider_sync')['created']
   + app(EducationInstitutionImportService::class)->import([], 'provider_sync')['updated'];
 }
 public function syncProducts(array $items): int {
  $count=0;
  foreach ($items as $item) {
   $code=trim((string)($item['code']??'')); $name=trim((string)($item['name']??''));
   if ($code===''||$name==='') continue;
   EducationProduct::updateOrCreate(['code'=>$code],[
    'name'=>$name,'category'=>$item['category']??'other','institution_id'=>$item['institution_id']??null,
    'provider_service_code'=>$item['provider_service_code']??null,'currency'=>$item['currency']??'NGN',
    'provider_amount_minor'=>$item['provider_amount_minor']??null,'selling_amount_minor'=>$item['selling_amount_minor']??null,
    'pricing_mode'=>$item['pricing_mode']??'fixed','requires_institution'=>$item['requires_institution']??false,
    'requires_student_reference'=>$item['requires_student_reference']??false,'requires_session'=>$item['requires_session']??false,
    'active'=>$item['active']??true,'fields'=>$item['fields']??null,'metadata'=>$item['metadata']??null,
   ]); $count++;
  }
  return $count;
 }
}