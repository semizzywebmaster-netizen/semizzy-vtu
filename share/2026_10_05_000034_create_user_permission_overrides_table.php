<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_permission_overrides')) {
            Schema::create('user_permission_overrides', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('permission', 120);
                $table->boolean('allowed')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'permission']);
                $table->index(['permission', 'allowed']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
