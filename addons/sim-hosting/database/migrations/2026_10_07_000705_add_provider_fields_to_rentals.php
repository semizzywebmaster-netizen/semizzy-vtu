<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('sim_hosting_rentals', function(Blueprint $table){$table->string('provider_reference',160)->nullable()->after('reference');$table->string('provider_status',40)->nullable()->after('status');$table->timestamp('provider_checked_at')->nullable()->after('expires_at');$table->index(['provider_reference','provider_status']);}); }
 public function down(): void { Schema::table('sim_hosting_rentals', function(Blueprint $table){$table->dropIndex(['provider_reference','provider_status']);$table->dropColumn(['provider_reference','provider_status','provider_checked_at']);});}
};