<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cac_order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cac_order_id')->constrained('cac_orders')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('system');
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['cac_order_id', 'created_at']);
            $table->index(['to_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cac_order_status_histories');
    }
};
