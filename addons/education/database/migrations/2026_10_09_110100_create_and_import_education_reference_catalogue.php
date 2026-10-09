<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('education_reference_catalogue', function(Blueprint $t) {
   $t->id(); $t->string('kind',32)->index(); $t->string('category',80)->nullable()->index();
   $t->string('name',220); $t->string('short_name',80)->nullable(); $t->string('state',80)->nullable()->index();
   $t->string('country',100)->nullable(); $t->string('official_url',500)->nullable(); $t->string('source_url',500)->nullable();
   $t->json('metadata')->nullable(); $t->boolean('is_active')->default(true)->index();
   $t->timestamps(); $t->unique(['kind','category','name'],'edu_ref_kind_category_name_unique');
  });
  $catalogue = require base_path('addons/education/data/reference-catalogue.php');
  $now = now();
  $rows = [];
  foreach (($catalogue['exam_bodies'] ?? []) as $entry) $rows[] = [
   'kind'=>'exam_body','category'=>'national_or_international','name'=>$entry['name'],'short_name'=>$entry['short_name'] ?? null,'state'=>null,
   'country'=>$entry['country'] ?? null,'official_url'=>$entry['official_url'] ?? null,'source_url'=>$entry['source_url'] ?? null,
   'metadata'=>json_encode(['catalogue_source'=>'curated_official_references']), 'is_active'=>true,'created_at'=>$now,'updated_at'=>$now,
  ];
  foreach (($catalogue['exam_types'] ?? []) as $entry) $rows[] = [
   'kind'=>'exam_type','category'=>null,'name'=>$entry['name'],'short_name'=>null,'state'=>null,'country'=>null,
   'official_url'=>null,'source_url'=>null,'metadata'=>json_encode(['exam_body'=>$entry['body'] ?? null,'catalogue_source'=>'curated_official_references']),
   'is_active'=>true,'created_at'=>$now,'updated_at'=>$now,
  ];
  foreach (($catalogue['schools'] ?? []) as $entry) $rows[] = [
   'kind'=>'school','category'=>$entry['category'] ?? 'other_tertiary_institution','name'=>$entry['name'],'short_name'=>null,
   'state'=>$entry['state'] ?? null,'country'=>'Nigeria','official_url'=>$entry['official_url'] ?? null,
   'source_url'=>self::sourceForCategory($entry['category'] ?? ''),'metadata'=>json_encode(['catalogue_source'=>'official_regulator_directories','verification'=>'initial_reference_entry']),
   'is_active'=>true,'created_at'=>$now,'updated_at'=>$now,
  ];
  foreach (array_chunk($rows,100) as $chunk) DB::table('education_reference_catalogue')->insert($chunk);
 }
 private static function sourceForCategory(string $category): string {
  if (str_contains($category,'university')) return 'https://www.nuc.edu.ng/approved-affiliations/';
  if (str_contains($category,'polytechnic')) return 'https://web.nbte.gov.ng/tvet%20institutions';
  if (str_contains($category,'college_education')) return 'https://www.ncce.gov.ng/AccreditedColleges';
  return 'https://web.nbte.gov.ng/tvet%20institutions';
 }
 public function down(): void { Schema::dropIfExists('education_reference_catalogue'); }
};
