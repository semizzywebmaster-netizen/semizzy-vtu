<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users')) return;

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'avatar_path')) $table->string('avatar_path', 500)->nullable();
            if (! Schema::hasColumn('users', 'address')) $table->text('address')->nullable();
            if (! Schema::hasColumn('users', 'city')) $table->string('city', 100)->nullable();
            if (! Schema::hasColumn('users', 'state')) $table->string('state', 100)->nullable();
            if (! Schema::hasColumn('users', 'country')) $table->string('country', 100)->nullable();
            if (! Schema::hasColumn('users', 'postal_code')) $table->string('postal_code', 30)->nullable();
            if (! Schema::hasColumn('users', 'date_of_birth')) $table->date('date_of_birth')->nullable();
            if (! Schema::hasColumn('users', 'gender')) $table->string('gender', 30)->nullable();
            if (! Schema::hasColumn('users', 'occupation')) $table->string('occupation', 120)->nullable();
            if (! Schema::hasColumn('users', 'identity_type')) $table->string('identity_type', 50)->nullable();
            if (! Schema::hasColumn('users', 'identity_number')) $table->string('identity_number', 120)->nullable();
            if (! Schema::hasColumn('users', 'identity_document_path')) $table->string('identity_document_path', 500)->nullable();
            if (! Schema::hasColumn('users', 'kyc_status')) $table->string('kyc_status', 30)->default('not_started')->index();
            if (! Schema::hasColumn('users', 'kyc_submitted_at')) $table->timestamp('kyc_submitted_at')->nullable();
            if (! Schema::hasColumn('users', 'kyc_reviewed_at')) $table->timestamp('kyc_reviewed_at')->nullable();
            if (! Schema::hasColumn('users', 'kyc_rejection_reason')) $table->text('kyc_rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) return;

        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'avatar_path','address','city','state','country','postal_code','date_of_birth','gender','occupation',
                'identity_type','identity_number','identity_document_path','kyc_status','kyc_submitted_at',
                'kyc_reviewed_at','kyc_rejection_reason'
            ] as $column) {
                if (Schema::hasColumn('users', $column)) $table->dropColumn($column);
            }
        });
    }
};
