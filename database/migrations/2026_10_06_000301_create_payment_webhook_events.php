<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 100);
            $table->string('event_id', 190);
            $table->string('event_type', 100)->nullable();
            $table->string('signature_hash', 128)->nullable();
            $table->string('processing_status', 30)->default('received');
            $table->string('payment_reference', 100)->nullable()->index();
            $table->json('payload')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_key', 'event_id']);
            $table->index(['processing_status', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('payment_webhook_events'); }
};
