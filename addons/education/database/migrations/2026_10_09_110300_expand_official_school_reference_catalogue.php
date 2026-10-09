<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up(): void {
  $catalogue = require base_path('addons/education/data/official-directory-expansion.php');
  $now = now();
  $inserted = 0;
  foreach (($catalogue['schools'] ?? []) as $entry) {
   $category = $entry['category'] ?? 'other_tertiary_institution';
   $name = trim((string) ($entry['name'] ?? ''));
   if ($name === '') continue;
   $key = hash('sha256', 'school|' . $category . '|' . $name);
   if (DB::table('education_reference_catalogue')->where('catalogue_key', $key)->exists()) continue;
   DB::table('education_reference_catalogue')->insert([
    'kind'=>'school','category'=>$category,'name'=>$name,'catalogue_key'=>$key,
    'short_name'=>$entry['short_name'] ?? null,'state'=>$entry['state'] ?? null,'country'=>'Nigeria',
    'official_url'=>$entry['official_url'] ?? null,
    'source_url'=>$entry['source_url'] ?? 'https://enuc.nuc.edu.ng/nus',
    'metadata'=>json_encode(['catalogue_source'=>'official_regulator_directory_expansion','verification'=>'directory_reference_seed']),
    'is_active'=>true,'created_by'=>null,'updated_by'=>null,'created_at'=>$now,'updated_at'=>$now,
   ]);
   $inserted++;
  }
  // Correct stale source links on existing records to the current regulator directory endpoints.
  DB::table('education_reference_catalogue')->where('kind','school')
   ->whereIn('category',['federal_university','state_university','private_university'])
   ->update(['source_url'=>'https://enuc.nuc.edu.ng/nus','updated_at'=>$now]);
  DB::table('education_reference_catalogue')->where('kind','school')
   ->where('category','college_education')
   ->update(['source_url'=>'https://ncce.gov.ng/AccreditedColleges','updated_at'=>$now]);
  DB::table('education_reference_catalogue')->where('kind','school')
   ->where(function($q){$q->where('category','like','%polytechnic%')->orWhere('category','like','%technical%');})
   ->update(['source_url'=>'https://web.nbte.gov.ng/tvet%20institutions','updated_at'=>$now]);
 }
 public function down(): void {
  $catalogue = require base_path('addons/education/data/official-directory-expansion.php');
  foreach (($catalogue['schools'] ?? []) as $entry) {
   $category = $entry['category'] ?? 'other_tertiary_institution';
   $name = trim((string) ($entry['name'] ?? ''));
   if ($name === '') continue;
   DB::table('education_reference_catalogue')->where('catalogue_key', hash('sha256', 'school|' . $category . '|' . $name))->delete();
  }
 }
};
