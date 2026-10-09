<?php
use Illuminate\Database\\Migrations\\Migration;
use Illuminate\Database\\Schema\\Blueprint;
use Illuminate\Support\\Facades\\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('website_sites',function(Blueprint $t){$t->string('published_at')->nullable();$t->string('active_domain')->nullable();$t->index(['status','published_at']);});
  Schema::table('website_pages',function(Blueprint $t){$t->unsignedInteger('sort_order')->default(0);$t->index(['website_site_id','sort_order']);});
  Schema::table('website_domains',function(Blueprint $t){$t->string('verification_method')->default('dns_txt');$t->index(['domain','status']);});
 };
 public function down(): void {
  Schema::table('website_domains',fn(Blueprint $t)=>$t->dropColumn('verification_method'));
  Schema::table('website_pages',fn(Blueprint $t)=>$t->dropColumn('sort_order'));
  Schema::table('website_sites',function(Blueprint $t){$t->dropIndex(['status','published_at']);$t->dropColumn(['published_at','active_domain']);});
 }
};