<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('website_sites', function(Blueprint $t){
   $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
   $t->string('name'); $t->string('slug'); $t->string('template_key')->default('modern-corporate');
   $t->string('status')->default('draft'); $t->string('subdomain')->nullable()->unique();
   $t->string('published_revision_id')->nullable(); $t->json('settings')->nullable();
   $t->timestamps(); $t->unique(['user_id','slug']); $t->index(['user_id','status']);
  });
  Schema::create('website_pages', function(Blueprint $t){
   $t->id(); $t->foreignId('website_site_id')->constrained('website_sites')->cascadeOnDelete();
   $t->string('title'); $t->string('slug'); $t->string('status')->default('draft');
   $t->boolean('is_home')->default(false); $t->json('content')->nullable(); $t->json('seo')->nullable();
   $t->timestamps(); $t->unique(['website_site_id','slug']); $t->index(['website_site_id','status']);
  });
  Schema::create('website_revisions', function(Blueprint $t){
   $t->id(); $t->foreignId('website_site_id')->constrained('website_sites')->cascadeOnDelete();
   $t->foreignId('website_page_id')->nullable()->constrained('website_pages')->nullOnDelete();
   $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
   $t->unsignedInteger('version'); $t->string('status')->default('draft'); $t->json('content')->nullable();
   $t->timestamps(); $t->unique(['website_site_id','website_page_id','version']); $t->index(['website_site_id','status']);
  });
  Schema::create('website_domains', function(Blueprint $t){
   $t->id(); $t->foreignId('website_site_id')->constrained('website_sites')->cascadeOnDelete();
   $t->string('domain')->unique(); $t->string('type')->default('custom'); $t->string('status')->default('pending');
   $t->string('verification_token')->nullable(); $t->timestamp('verified_at')->nullable(); $t->boolean('primary')->default(false);
   $t->json('metadata')->nullable(); $t->timestamps(); $t->index(['website_site_id','status']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('website_domains'); Schema::dropIfExists('website_revisions');
  Schema::dropIfExists('website_pages'); Schema::dropIfExists('website_sites');
 }
};