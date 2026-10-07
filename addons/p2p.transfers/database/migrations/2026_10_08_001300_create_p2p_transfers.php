<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('p2p_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('idempotency_key', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->string('currency', 3);
            $table->string('status', 24)->default('completed');
            $table->string('note', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['sender_id', 'created_at']);
            $table->index(['recipient_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->unique(['sender_id', 'idempotency_key']);
        });
    }

    public function down(): void { Schema::dropIfExists('p2p_transfers'); }
};
