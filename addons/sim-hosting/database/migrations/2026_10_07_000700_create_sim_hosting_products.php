<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sim_hosting_products', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 160);
            $table->string('country', 2)->default('NG');
            $table->string('network', 80)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('rental_price_minor');
            $table->unsignedInteger('rental_days')->default(30);
            $table->unsignedBigInteger('renewal_price_minor')->nullable();
            $table->unsignedInteger('max_rental_days')->nullable();
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['country','network','active']);
        });
    }
    public function down(): void { Schema::dropIfExists('sim_hosting_products'); }
};