<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('p2p_trade_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('side', 8)->default('sell');
            $table->string('asset_key', 100);
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('price_minor');
            $table->string('status', 24)->default('open');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['seller_id','status']);
            $table->index(['asset_key','side','status']);
        });

        Schema::create('p2p_trade_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('p2p_trade_listings')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('reference', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('price_minor');
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 120);
            $table->unsignedBigInteger('escrow_transaction_id')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['buyer_id','idempotency_key']);
            $table->index(['listing_id','status']);
            $table->index(['buyer_id','status']);
            $table->index(['seller_id','status']);
            $table->index(['expires_at','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_trade_offers');
        Schema::dropIfExists('p2p_trade_listings');
    }
};