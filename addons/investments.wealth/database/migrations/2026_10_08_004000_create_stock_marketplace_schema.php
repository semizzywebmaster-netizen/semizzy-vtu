<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('investment_securities', function (Blueprint $t) {
            $t->id();
            $t->string('symbol', 40);
            $t->string('name');
            $t->string('asset_type', 32);
            $t->string('market', 80)->nullable();
            $t->string('exchange', 80)->nullable();
            $t->string('isin', 32)->nullable();
            $t->string('currency', 3)->default('NGN');
            $t->string('country', 2)->nullable();
            $t->text('description')->nullable();
            $t->json('metadata')->nullable();
            $t->string('status', 24)->default('draft')->index();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();

            $t->unique(['symbol', 'market']);
            $t->index(['asset_type', 'status']);
            $t->index(['exchange', 'status']);
            $t->index('isin');
        });

        Schema::create('investment_market_quotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('security_id')->constrained('investment_securities')->cascadeOnDelete();
            $t->string('source', 120);
            $t->decimal('bid', 24, 8)->nullable();
            $t->decimal('ask', 24, 8)->nullable();
            $t->decimal('last_price', 24, 8)->nullable();
            $t->decimal('open_price', 24, 8)->nullable();
            $t->decimal('high_price', 24, 8)->nullable();
            $t->decimal('low_price', 24, 8)->nullable();
            $t->unsignedBigInteger('volume')->nullable();
            $t->string('currency', 3)->default('NGN');
            $t->timestamp('observed_at');
            $t->timestamp('expires_at')->nullable();
            $t->json('raw_metadata')->nullable();
            $t->timestamps();

            $t->index(['security_id', 'observed_at']);
            $t->index(['source', 'observed_at']);
        });

        Schema::create('investment_providers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('key')->unique();
            $t->string('provider_type', 32);
            $t->string('status', 24)->default('disabled')->index();
            $t->boolean('is_data_provider')->default(false);
            $t->boolean('is_execution_provider')->default(false);
            $t->boolean('verified')->default(false);
            $t->text('regulatory_note')->nullable();
            $t->json('capabilities')->nullable();
            $t->json('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('investment_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('security_id')->constrained('investment_securities')->restrictOnDelete();
            $t->foreignId('provider_id')->nullable()->constrained('investment_providers')->nullOnDelete();
            $t->string('reference')->unique();
            $t->string('client_order_id')->nullable()->unique();
            $t->string('side', 12);
            $t->string('order_type', 20)->default('market');
            $t->decimal('quantity', 24, 8);
            $t->decimal('limit_price', 24, 8)->nullable();
            $t->decimal('estimated_amount', 24, 8)->nullable();
            $t->string('currency', 3)->default('NGN');
            $t->string('status', 32)->default('pending')->index();
            $t->string('provider_order_id')->nullable()->index();
            $t->string('provider_status', 40)->nullable();
            $t->text('failure_reason')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'status']);
            $t->index(['security_id', 'status']);
        });

        Schema::create('investment_executions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investment_order_id')->constrained('investment_orders')->cascadeOnDelete();
            $t->string('provider_execution_id')->nullable()->unique();
            $t->decimal('quantity', 24, 8);
            $t->decimal('price', 24, 8);
            $t->decimal('gross_amount', 24, 8);
            $t->decimal('fees', 24, 8)->default(0);
            $t->string('currency', 3)->default('NGN');
            $t->timestamp('executed_at');
            $t->json('metadata')->nullable();
            $t->timestamps();

            $t->index(['investment_order_id', 'executed_at']);
        });

        Schema::create('investment_holdings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('security_id')->constrained('investment_securities')->restrictOnDelete();
            $t->decimal('quantity', 24, 8)->default(0);
            $t->decimal('average_cost', 24, 8)->default(0);
            $t->decimal('cost_basis', 24, 8)->default(0);
            $t->string('currency', 3)->default('NGN');
            $t->timestamp('last_reconciled_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'security_id']);
            $t->index(['security_id', 'last_reconciled_at']);
        });

        Schema::create('investment_corporate_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('security_id')->constrained('investment_securities')->cascadeOnDelete();
            $t->string('action_type', 32);
            $t->string('reference')->unique();
            $t->date('record_date')->nullable();
            $t->date('ex_date')->nullable();
            $t->date('payment_date')->nullable();
            $t->decimal('value', 24, 8)->nullable();
            $t->string('currency', 3)->nullable();
            $t->text('description')->nullable();
            $t->string('status', 24)->default('announced')->index();
            $t->json('metadata')->nullable();
            $t->timestamps();

            $t->index(['security_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_corporate_actions');
        Schema::dropIfExists('investment_holdings');
        Schema::dropIfExists('investment_executions');
        Schema::dropIfExists('investment_orders');
        Schema::dropIfExists('investment_providers');
        Schema::dropIfExists('investment_market_quotes');
        Schema::dropIfExists('investment_securities');
    }
};
