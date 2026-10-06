<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('bulk_sms_sender_ids',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->string('sender',40);$t->string('status',30)->default('pending');$t->text('notes')->nullable();$t->timestamps();$t->unique(['user_id','sender']);});}};
