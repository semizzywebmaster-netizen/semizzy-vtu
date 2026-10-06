<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('bulk_sms_contacts',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('name')->nullable();$t->string('phone',32);$t->string('group_name',100)->nullable();$t->boolean('subscribed')->default(true);$t->timestamps();$t->index(['user_id','group_name']);$t->unique(['user_id','phone']);});}};
