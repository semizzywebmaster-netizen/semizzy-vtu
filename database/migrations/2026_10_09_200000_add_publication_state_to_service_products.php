<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_products', 'publication_status')) {
            Schema::table('service_products', function (Blueprint $table): void {
                $table->string('publication_status', 20)->default('draft')->index();
            });
        }
        if (! Schema::hasColumn('service_products', 'published_at')) {
            Schema::table('service_products', function (Blueprint $table): void {
                $table->timestamp('published_at')->nullable();
            });
        }
        if (! Schema::hasColumn('service_products', 'published_by')) {
            Schema::table('service_products', function (Blueprint $table): void {
                $table->unsignedBigInteger('published_by')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('service_products', 'publication_blockers')) {
            Schema::table('service_products', function (Blueprint $table): void {
                $table->json('publication_blockers')->nullable();
            });
        }

        // Preserve the public state of products already enabled before this lifecycle existed.
        DB::table('service_products')
            ->where('enabled', true)
            ->where('publication_status', 'draft')
            ->update(['publication_status' => 'published']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_products', 'publication_blockers')) {
            Schema::table('service_products', fn (Blueprint $table) => $table->dropColumn('publication_blockers'));
        }
        if (Schema::hasColumn('service_products', 'published_by')) {
            Schema::table('service_products', function (Blueprint $table): void { $table->dropIndex(['published_by']); $table->dropColumn('published_by'); });
        }
        if (Schema::hasColumn('service_products', 'published_at')) {
            Schema::table('service_products', fn (Blueprint $table) => $table->dropColumn('published_at'));
        }
        if (Schema::hasColumn('service_products', 'publication_status')) {
            Schema::table('service_products', function (Blueprint $table): void { $table->dropIndex(['publication_status']); $table->dropColumn('publication_status'); });
        }
    }
};
