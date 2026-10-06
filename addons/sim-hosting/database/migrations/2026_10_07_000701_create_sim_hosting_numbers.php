<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sim_hosting_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sim_hosting_product_id')->constrained('sim_hosting_products')->cascadeOnDelete();
            $table->string('provider_reference', 160)->nullable()->index();
            $table->string('number', 80)->unique();
            $table->string('country', 2)->default('NG');
            $table->string('network', 80)->nullable();
            $table->string('status', 30)->default('available');
            $table->timestamp('last_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['sim_hosting_product_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('sim_hosting_numbers'); }
};