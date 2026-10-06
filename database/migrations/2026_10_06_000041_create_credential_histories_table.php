<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credential_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_type', 30);
            $table->string('credential_hash', 255);
            $table->timestamps();
            $table->index(['user_id', 'credential_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_histories');
    }
};