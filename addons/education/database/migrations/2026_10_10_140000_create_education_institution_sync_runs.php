<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('education_institution_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 100)->index();
            $table->string('status', 20)->default('running')->index();
            $table->unsignedInteger('pages_processed')->default(0);
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->unsignedInteger('records_rejected')->default(0);
            $table->unsignedInteger('minimum_expected_records')->default(1);
            $table->unsignedInteger('expected_total')->nullable();
            $table->string('next_cursor', 500)->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['source', 'status', 'started_at'], 'edu_sync_source_status_started_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('education_institution_sync_runs')
            && DB::table('education_institution_sync_runs')->exists()) {
            throw new RuntimeException('Cannot remove institution sync history while run records exist; export or reconcile them first.');
        }

        Schema::dropIfExists('education_institution_sync_runs');
    }
};
