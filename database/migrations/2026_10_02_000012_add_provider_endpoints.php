<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void { Schema::table('api_providers', function(Blueprint $table): void { $table->json('endpoints')->nullable()->after('capabilities'); }); }
 public function down(): void { Schema::table('api_providers', fn(Blueprint $table) => $table->dropColumn('endpoints')); }
};
