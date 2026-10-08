<?php
namespace Semizzy\Addons\Education\Services;

use Semizzy\Addons\Education\Models\EducationInstitution;
use Illuminate\Support\Str;
class EducationInstitutionImportService
{
 public const CATEGORIES = [
  'university','polytechnic','college_of_education','college_of_agriculture',
  'college_of_health','college_of_nursing','monotechnic','innovation_enterprise',
  'vocational_enterprise','technical_college','rtc','other',
 ];
 public const OWNERSHIPS = ['federal','state','private','public','community','faith_based','other'];
 public function import(array $records, string $source='manual'): array
 {
  $created=$updated=$skipped=0;
  foreach ($records as $record) {
   $name=trim((string)($record['name'] ?? ''));
   if ($name==='') { $skipped++; continue; }
   $category=$this->normalizeCategory($record['category'] ?? $record['type'] ?? null);
   $ownership=$this->normalizeOwnership($record['ownership'] ?? null);
   $code=$this->code($record['code'] ?? $name);
   $payload=[
    'code'=>$code,'name'=>$name,'type'=>$category,'category'=>$category,'ownership'=>$ownership,
    'accrediting_body'=>$record['accrediting_body'] ?? $this->defaultAccreditor($category),
    'state'=>$record['state'] ?? null,'lga'=>$record['lga'] ?? null,'city'=>$record['city'] ?? null,
    'country'=>$record['country'] ?? 'Nigeria','website'=>$record['website'] ?? null,
    'external_id'=>$record['external_id'] ?? null,
    'established_year'=>isset($record['established_year']) ? (int)$record['established_year'] : null,
    'active'=>array_key_exists('active',$record) ? (bool)$record['active'] : true,
    'metadata'=>array_merge((array)($record['metadata'] ?? []), ['import_source'=>$source]),
    'classification'=>['category'=>$category,'ownership'=>$ownership,'source'=>$source],
   ];
   $model=EducationInstitution::withTrashed()->where('code',$code)->first();
   if ($model) {
    if ($model->trashed()) $model->restore();
    $model->fill($payload)->save(); $updated++;
   } else {
    EducationInstitution::create($payload); $created++;
   }
  }
  return compact('created','updated','skipped');
 }
 private function code(string $value): string { return substr(strtoupper(Str::slug($value,'-')),0,80); }
 private function normalizeCategory($value): string {
  $v=strtolower(trim((string)$value));
  $map=[
   'university'=>'university','universities'=>'university','polytechnic'=>'polytechnic','polytechnics'=>'polytechnic',
   'college'=>'college_of_education','college of education'=>'college_of_education','college of agriculture'=>'college_of_agriculture',
   'college of health'=>'college_of_health','college of health sciences and technology'=>'college_of_health',
   'college of nursing'=>'college_of_nursing','nursing'=>'college_of_nursing','monotechnic'=>'monotechnic',
   'specialised institution'=>'monotechnic','innovation enterprise institution'=>'innovation_enterprise','iei'=>'innovation_enterprise',
   'vocational enterprise institution'=>'vocational_enterprise','technical college'=>'technical_college','rtc'=>'rtc',
   'technical and vocational'=>'rtc',
  ];
  return $map[$v] ?? 'other';
 }
 private function normalizeOwnership($value): string {
  return match (strtolower(trim((string)$value))) {
   'federal','federal government'=>'federal','state','state government'=>'state','private'=>'private','public'=>'public',
   'community'=>'community','faith','faith based','faith-based'=>'faith_based',default=>'other',
  };
 }
 private function defaultAccreditor(string $category): ?string { return $category==='university' ? 'NUC' : ($category==='other' ? null : 'NBTE'); }
}