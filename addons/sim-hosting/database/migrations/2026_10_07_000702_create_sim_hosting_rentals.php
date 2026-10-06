<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sim_hosting_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sim_hosting_product_id')->constrained('sim_hosting_products')->restrictOnDelete();
            $table->foreignId('sim_hosting_number_id')->constrained('sim_hosting_numbers')->restrictOnDelete();
            $table->string('reference', 80)->unique();
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedInteger('rental_days');
            $table->string('status', 30)->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id','status']);
            $table->index(['expires_at','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('sim_hosting_rentals'); }
};