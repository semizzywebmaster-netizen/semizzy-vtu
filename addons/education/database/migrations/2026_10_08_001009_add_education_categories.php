<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('education_products', function (Blueprint $table) {
            $table->string('category', 40)->default('school_admission')->index()->after('name');
        });

        Schema::table('education_past_questions', function (Blueprint $table) {
            $table->string('category', 40)->default('exam_past_questions')->index()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('education_past_questions', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('education_products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
