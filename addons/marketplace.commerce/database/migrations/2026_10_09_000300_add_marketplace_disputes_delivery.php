<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->string('shipping_carrier', 120)->nullable();
            $table->string('tracking_number', 180)->nullable();
            $table->text('tracking_url')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
        });

        Schema::create('marketplace_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 80);
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->string('resolution', 40)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_disputes');
        Schema::table('marketplace_orders', function (Blueprint $table): void {
            $table->dropColumn(['shipping_carrier', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at']);
        });
    }
};
