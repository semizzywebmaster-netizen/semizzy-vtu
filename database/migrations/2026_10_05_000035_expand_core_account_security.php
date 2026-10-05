<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users','account_type')) $table->string('account_type',32)->default('personal')->index();
            if (!Schema::hasColumn('users','business_name')) $table->string('business_name',180)->nullable()->index();
            if (!Schema::hasColumn('users','business_registration_number')) $table->string('business_registration_number',100)->nullable();
            if (!Schema::hasColumn('users','business_type')) $table->string('business_type',100)->nullable();
            if (!Schema::hasColumn('users','business_address')) $table->text('business_address')->nullable();
            if (!Schema::hasColumn('users','business_state')) $table->string('business_state',100)->nullable();
            if (!Schema::hasColumn('users','business_country')) $table->string('business_country',100)->nullable();
            if (!Schema::hasColumn('users','merchant_verified_at')) $table->timestamp('merchant_verified_at')->nullable();
            if (!Schema::hasColumn('users','tier_upgrade_status')) $table->string('tier_upgrade_status',32)->default('none')->index();
            if (!Schema::hasColumn('users','two_factor_enabled')) $table->boolean('two_factor_enabled')->default(false);
            if (!Schema::hasColumn('users','two_factor_secret')) $table->text('two_factor_secret')->nullable();
            if (!Schema::hasColumn('users','transaction_pin_hash')) $table->text('transaction_pin_hash')->nullable();
            if (!Schema::hasColumn('users','security_lock_until')) $table->timestamp('security_lock_until')->nullable()->index();
            if (!Schema::hasColumn('users','onboarding_completed_at')) $table->timestamp('onboarding_completed_at')->nullable();
            if (!Schema::hasColumn('users','last_login_at')) $table->timestamp('last_login_at')->nullable()->index();
            if (!Schema::hasColumn('users','last_login_ip')) $table->string('last_login_ip',45)->nullable();
        });

        if (!Schema::hasTable('username_histories')) {
            Schema::create('username_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('username',80);
                $table->string('reason',80)->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id','created_at']);
            });
        }

        if (!Schema::hasTable('otp_challenges')) {
            Schema::create('otp_challenges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('channel',20);
                $table->string('purpose',50);
                $table->string('destination',190)->nullable();
                $table->string('code_hash',255);
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('max_attempts')->default(5);
                $table->string('ip_address',45)->nullable();
                $table->timestamps();
                $table->index(['user_id','purpose','expires_at']);
            });
        }

        if (!Schema::hasTable('login_activities')) {
            Schema::create('login_activities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('identifier',190)->nullable();
                $table->string('status',30);
                $table->string('ip_address',45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('risk_level',20)->default('normal');
                $table->string('reason',190)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id','created_at']);
                $table->index(['ip_address','created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activities');
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('username_histories');
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'account_type','business_name','business_registration_number','business_type','business_address',
                'business_state','business_country','merchant_verified_at','tier_upgrade_status','two_factor_enabled',
                'two_factor_secret','transaction_pin_hash','security_lock_until','onboarding_completed_at',
                'last_login_at','last_login_ip'
            ] as $column) {
                if (Schema::hasColumn('users',$column)) $table->dropColumn($column);
            }
        });
    }
};