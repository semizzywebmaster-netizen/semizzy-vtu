<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void
 {
  Schema::create('ad_types', function (Blueprint $table): void {
   $table->id();
   $table->string('key', 100)->unique();
   $table->string('name', 160);
   $table->text('description')->nullable();
   $table->string('format_family', 40)->default('native');
   $table->json('pricing_models')->nullable();
   $table->json('eligible_surfaces')->nullable();
   $table->boolean('requires_creative')->default(true);
   $table->boolean('supports_targeting')->default(false);
   $table->boolean('requires_integration')->default(false);
   $table->string('integration_key', 120)->nullable();
   $table->boolean('is_system')->default(false);
   $table->boolean('is_active')->default(false)->index();
   $table->timestamps();
  });
  $now=now();
  foreach ([
   ['key'=>'native','name'=>'Native ad','description'=>'Ad that matches the surrounding content layout.','format_family'=>'native','pricing_models'=>json_encode(['cpc','cpm','flat']),'eligible_surfaces'=>json_encode(['home','marketplace','feed','search']),'requires_creative'=>true,'supports_targeting'=>true,'requires_integration'=>false,'integration_key'=>null,'is_system'=>true,'is_active'=>true],
   ['key'=>'banner','name'=>'Banner ad','description'=>'Image-based banner placement.','format_family'=>'banner','pricing_models'=>json_encode(['cpm','flat']),'eligible_surfaces'=>json_encode(['home','marketplace','dashboard']),'requires_creative'=>true,'supports_targeting'=>true,'requires_integration'=>false,'integration_key'=>null,'is_system'=>true,'is_active'=>true],
   ['key'=>'sponsored_listing','name'=>'Sponsored listing','description'=>'Clearly labelled promoted marketplace listing.','format_family'=>'listing','pricing_models'=>json_encode(['cpc','flat']),'eligible_surfaces'=>json_encode(['marketplace','search','category']),'requires_creative'=>false,'supports_targeting'=>true,'requires_integration'=>false,'integration_key'=>null,'is_system'=>true,'is_active'=>true],
   ['key'=>'video','name'=>'Video ad','description'=>'Video creative; serving remains disabled until a video delivery adapter is available.','format_family'=>'video','pricing_models'=>json_encode(['cpm','flat']),'eligible_surfaces'=>json_encode(['feed','video']),'requires_creative'=>true,'supports_targeting'=>true,'requires_integration'=>true,'integration_key'=>'video.delivery.v1','is_system'=>true,'is_active'=>false],
  ] as $row) {
   DB::table('ad_types')->insert($row+['created_at'=>$now,'updated_at'=>$now]);
  }
 }
 public function down(): void
 {
  Schema::dropIfExists('ad_types');
 }
};
