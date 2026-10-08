<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::table('event_organizers',function(Blueprint $t){$t->timestamp('submitted_at')->nullable()->after('verified_at');$t->text('verification_note')->nullable()->after('submitted_at');});
  Schema::table('events',function(Blueprint $t){$t->timestamp('submitted_at')->nullable()->after('published_at');$t->timestamp('reviewed_at')->nullable()->after('submitted_at');$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('reviewed_at');$t->text('review_note')->nullable()->after('reviewed_by');});
 }
 public function down():void{
  Schema::table('events',function(Blueprint $t){$t->dropForeign(['reviewed_by']);$t->dropColumn(['submitted_at','reviewed_at','reviewed_by','review_note']);});
  Schema::table('event_organizers',function(Blueprint $t){$t->dropColumn(['submitted_at','verification_note']);});
 }
};