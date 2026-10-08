<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_organizers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website_url')->nullable();
            $table->string('verification_status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['user_id','verification_status']);
        });

        Schema::create('event_venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Nigeria');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('map_url')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();
            $table->index(['country','state','city']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('event_organizers')->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('event_venues')->nullOnDelete();
            $table->string('category', 80);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('event_type')->default('physical');
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('NGN');
            $table->string('timezone')->default('Africa/Lagos');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('virtual_url')->nullable();
            $table->string('virtual_platform')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->json('gallery')->nullable();
            $table->json('age_policy')->nullable();
            $table->json('policies')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status','starts_at']);
            $table->index(['category','status']);
            $table->index(['organizer_id','status']);
        });

        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained('event_venues')->nullOnDelete();
            $table->string('status')->default('scheduled');
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();
            $table->index(['event_id','starts_at']);
        });

        Schema::create('event_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('occurrence_id')->nullable()->constrained('event_occurrences')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('ticket_mode')->default('single');
            $table->string('access_type')->default('paid');
            $table->decimal('price', 18, 2)->default(0);
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('per_order_min')->default(1);
            $table->unsignedInteger('per_order_max')->nullable();
            $table->timestamp('sales_start_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();
            $table->json('perks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['event_id','is_active']);
            $table->index(['occurrence_id','is_active']);
        });

        Schema::create('event_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('pending');
            $table->string('currency', 3)->default('NGN');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('fees', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('payment_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['user_id','status']);
            $table->index(['event_id','status']);
        });

        Schema::create('event_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('event_orders')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('event_ticket_types')->restrictOnDelete();
            $table->foreignId('occurrence_id')->nullable()->constrained('event_occurrences')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });

        Schema::create('event_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('event_order_items')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('event_ticket_types')->restrictOnDelete();
            $table->foreignId('occurrence_id')->nullable()->constrained('event_occurrences')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('qr_token')->unique();
            $table->string('status')->default('issued');
            $table->string('attendee_name')->nullable();
            $table->string('attendee_email')->nullable();
            $table->string('attendee_phone')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['event_id','status']);
            $table->index(['occurrence_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tickets');
        Schema::dropIfExists('event_order_items');
        Schema::dropIfExists('event_orders');
        Schema::dropIfExists('event_ticket_types');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_venues');
        Schema::dropIfExists('event_organizers');
    }
};
