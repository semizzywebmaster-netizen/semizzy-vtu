<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('insurance_claims',function(Blueprint $t){$t->string('idempotency_key')->nullable()->unique();$t->string('provider_status')->nullable();$t->timestamp('submitted_to_provider_at')->nullable();}); }
 public function down(): void { Schema::table('insurance_claims',function(Blueprint $t){$t->dropUnique(['idempotency_key']);$t->dropColumn(['provider_status','submitted_to_provider_at']);}); }
};