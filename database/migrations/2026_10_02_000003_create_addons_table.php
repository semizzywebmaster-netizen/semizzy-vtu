<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('name');
            $table->string('version')->default('0.1.0');
            $table->string('status')->default('draft')->index();
            $table->string('compatibility_constraint')->nullable();
            $table->json('dependencies')->nullable();
            $table->json('permissions')->nullable();
            $table->json('navigation')->nullable();
            $table->json('settings_schema')->nullable();
            $table->string('package_checksum')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'identifier']);
        });
        Schema::create('addon_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('addon_id')->nullable()->constrained('addons')->nullOnDelete();
            $table->string('addon_identifier');
            $table->string('event');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['addon_identifier', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_lifecycle_events');
        Schema::dropIfExists('addons');
    }
};
