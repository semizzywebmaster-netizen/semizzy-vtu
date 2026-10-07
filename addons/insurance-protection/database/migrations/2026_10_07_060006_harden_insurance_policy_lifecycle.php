<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('insurance_policies',function(Blueprint $t){$t->string('cancel_reason')->nullable();$t->timestamp('renewal_due_at')->nullable()->index();$t->timestamp('renewed_at')->nullable();}); }
 public function down(): void { Schema::table('insurance_policies',function(Blueprint $t){$t->dropColumn(['cancel_reason','renewal_due_at','renewed_at']);}); }
};