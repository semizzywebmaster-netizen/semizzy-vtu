<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sim_hosting_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sim_hosting_rental_id')->constrained('sim_hosting_rentals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation_key', 180)->unique();
            $table->string('reference', 100)->unique();
            $table->string('type', 40);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id','type']);
        });
    }
    public function down(): void { Schema::dropIfExists('sim_hosting_movements'); }
};