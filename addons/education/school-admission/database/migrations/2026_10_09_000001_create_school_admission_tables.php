<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('school_admission_institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 200);
            $table->string('institution_type', 40)->default('university');
            $table->string('country_code', 2)->default('NG');
            $table->string('state', 100)->nullable();
            $table->string('official_website')->nullable();
            $table->string('admissions_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('school_admission_programmes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained('school_admission_institutions')->cascadeOnDelete();
            $table->string('code', 80)->nullable();
            $table->string('name', 200);
            $table->string('level', 40)->default('undergraduate');
            $table->string('study_mode', 40)->default('full_time');
            $table->boolean('active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'name']);
        });

        Schema::create('school_admission_products', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->foreignId('institution_id')->nullable()->constrained('school_admission_institutions')->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('school_admission_programmes')->nullOnDelete();
            $table->string('service_type', 50)->default('application_form')->index();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('provider_product_code', 160)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('display_order')->default(100);
            $table->json('requirements')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('provider_id')->references('id')->on('api_providers')->nullOnDelete();
        });

        Schema::create('school_admission_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('school_admission_products')->restrictOnDelete();
            $table->unsignedBigInteger('wallet_account_id');
            $table->string('reference', 80)->unique();
            $table->string('idempotency_key', 120);
            $table->string('provider_reference', 160)->nullable()->index();
            $table->string('candidate_identifier', 160)->nullable()->index();
            $table->json('customer_data')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('NGN');
            $table->json('provider_data')->nullable();
            $table->text('error')->nullable();
            $table->boolean('requery_required')->default(false)->index();
            $table->timestamp('next_requery_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->foreign('wallet_account_id')->references('id')->on('wallet_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_admission_transactions');
        Schema::dropIfExists('school_admission_products');
        Schema::dropIfExists('school_admission_programmes');
        Schema::dropIfExists('school_admission_institutions');
    }
};