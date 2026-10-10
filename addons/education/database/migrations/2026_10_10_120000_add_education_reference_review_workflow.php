<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('education_reference_catalogue', function (Blueprint $table): void {
            $table->string('review_status', 20)->default('approved')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('education_reference_catalogue', function (Blueprint $table): void {
            $table->dropColumn(['review_status', 'reviewed_by', 'reviewed_at', 'review_notes']);
        });
    }
};
