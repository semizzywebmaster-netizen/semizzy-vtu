<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void {
 Schema::create('bulk_sms_products',function(Blueprint $t){$t->id();$t->string('key',80)->unique();$t->string('name');$t->string('currency',3)->default('NGN');$t->unsignedBigInteger('price_per_sms_minor');$t->unsignedInteger('max_recipients')->default(10000);$t->unsignedBigInteger('provider_id')->nullable();$t->boolean('active')->default(true);$t->timestamps();$t->foreign('provider_id')->references('id')->on('api_providers')->nullOnDelete();});
}};
