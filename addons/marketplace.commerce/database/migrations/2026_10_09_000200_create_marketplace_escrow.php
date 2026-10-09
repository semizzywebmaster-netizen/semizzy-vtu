<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_escrows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('marketplace_orders')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('currency', 3)->default('NGN');
            $table->string('gross_minor', 30);
            $table->string('seller_net_minor', 30);
            $table->string('platform_profit_minor', 30);
            $table->string('status', 30)->default('held')->index();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->foreignId('buyer_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_escrows');
    }
};
