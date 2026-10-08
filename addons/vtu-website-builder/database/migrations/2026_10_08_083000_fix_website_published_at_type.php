<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('website_sites', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('website_sites', function (Blueprint $table) {
            $table->string('published_at')->nullable()->change();
        });
    }
};
