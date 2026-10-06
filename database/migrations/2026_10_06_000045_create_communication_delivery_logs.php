<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('communication_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained('communication_campaigns')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel',32);
            $table->string('status',32);
            $table->foreignId('provider_id')->nullable()->constrained('api_providers')->nullOnDelete();
            $table->string('provider_reference')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_id','channel','status']);
            $table->index(['user_id','channel','created_at']);
            $table->index(['provider_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_delivery_logs');
    }
};
