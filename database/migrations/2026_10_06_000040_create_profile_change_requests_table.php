<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('profile_change_requests')) {
            return;
        }

        Schema::create('profile_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_type', 40)->index(); // personal, business
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected
            $table->json('requested_changes');
            $table->text('reason');
            $table->string('business_entity_type', 50)->nullable();
            $table->string('business_registration_number', 120)->nullable();
            $table->string('business_tax_id', 120)->nullable();
            $table->string('business_registered_name', 180)->nullable();
            $table->string('business_registered_address', 500)->nullable();
            $table->string('business_state', 100)->nullable();
            $table->string('business_country', 100)->nullable();
            $table->json('business_documents')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'request_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_change_requests');
    }
};
