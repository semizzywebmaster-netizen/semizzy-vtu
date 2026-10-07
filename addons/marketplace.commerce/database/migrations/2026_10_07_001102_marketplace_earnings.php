<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('gross_minor', 30);
            $table->string('fee_minor', 30)->default('0');
            $table->string('net_minor', 30);
            $table->string('currency', 3);
            $table->string('status', 20)->default('credited');
            $table->timestamps();
            $table->unique('order_id');
            $table->index(['seller_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_earnings');
    }
};
