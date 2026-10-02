<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
 public function up():void{
  Schema::create('price_rules',function(Blueprint $t):void{
   $t->id();$t->string('scope_type');$t->unsignedBigInteger('scope_id')->nullable();$t->string('customer_tier')->nullable();
   $t->string('rule_type')->default('percentage');$t->decimal('fixed_fee',20,6)->default(0);$t->decimal('percentage',12,6)->default(0);
   $t->decimal('minimum_price',20,6)->nullable();$t->decimal('maximum_price',20,6)->nullable();$t->unsignedInteger('rounding_increment')->default(0);
   $t->boolean('enabled')->default(true);$t->timestamp('effective_from')->nullable();$t->timestamp('effective_to')->nullable();$t->unsignedInteger('priority')->default(100);
   $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();
   $t->index(['scope_type','scope_id','customer_tier','enabled']);$t->index(['effective_from','effective_to']);
  });
 }
 public function down():void{Schema::dropIfExists('price_rules');}
};
