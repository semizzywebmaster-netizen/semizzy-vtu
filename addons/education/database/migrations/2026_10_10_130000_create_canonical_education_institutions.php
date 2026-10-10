<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('education_reference_catalogue')) {
            throw new RuntimeException('Education reference catalogue must be installed before the canonical institution migration.');
        }

        Schema::create('education_institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('source_key', 180)->nullable()->unique();
            $table->string('name', 220)->index();
            $table->string('type', 40)->default('other')->index();
            $table->string('category', 80)->nullable()->index();
            $table->string('ownership', 40)->nullable()->index();
            $table->string('accrediting_body', 80)->nullable()->index();
            $table->unsignedSmallInteger('established_year')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 80)->nullable()->index();
            $table->string('lga', 100)->nullable();
            $table->string('country', 100)->default('Nigeria');
            $table->string('website', 500)->nullable();
            $table->string('external_id', 120)->nullable()->index();
            $table->string('source_url', 500)->nullable();
            $table->string('import_source', 100)->nullable()->index();
            $table->string('review_status', 20)->default('approved')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->boolean('active')->default(false)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->json('classification')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $hasReviewState = Schema::hasColumn('education_reference_catalogue', 'review_status');

        DB::table('education_reference_catalogue')
            ->where('kind', 'school')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($hasReviewState): void {
                foreach ($rows as $row) {
                    $key = 'catalogue:' . (string) $row->catalogue_key;
                    $slug = Str::slug((string) $row->name);
                    $code = substr($slug !== '' ? $slug : 'institution', 0, 70)
                        . '-' . substr(hash('sha256', (string) $row->catalogue_key), 0, 8);
                    $reviewStatus = $hasReviewState ? (string) ($row->review_status ?? 'approved') : 'approved';
                    $active = (bool) $row->is_active && $reviewStatus === 'approved';
                    $metadata = json_decode((string) ($row->metadata ?? '{}'), true);
                    $metadata = is_array($metadata) ? $metadata : [];

                    DB::table('education_institutions')->updateOrInsert(
                        ['source_key' => $key],
                        [
                            'code' => $code,
                            'name' => mb_substr((string) $row->name, 0, 220),
                            'type' => self::institutionType((string) ($row->category ?? '')),
                            'category' => $row->category,
                            'state' => $row->state,
                            'country' => $row->country ?: 'Nigeria',
                            'website' => $row->official_url,
                            'source_url' => $row->source_url,
                            'import_source' => 'education_reference_catalogue',
                            'review_status' => $reviewStatus,
                            'reviewed_by' => $hasReviewState ? ($row->reviewed_by ?? null) : null,
                            'reviewed_at' => $hasReviewState ? ($row->reviewed_at ?? null) : null,
                            'review_notes' => $hasReviewState ? ($row->review_notes ?? null) : null,
                            'active' => $active,
                            'classification' => json_encode(['legacy_catalogue_id' => $row->id, 'legacy_kind' => $row->kind]),
                            'metadata' => json_encode($metadata + ['migrated_from_catalogue' => true]),
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    private static function institutionType(string $category): string
    {
        $value = strtolower($category);
        if (str_contains($value, 'university')) return 'university';
        if (str_contains($value, 'polytechnic')) return 'polytechnic';
        if (str_contains($value, 'college')) return 'college';
        if (str_contains($value, 'monotechnic')) return 'monotechnic';
        return 'other';
    }

    public function down(): void
    {
        Schema::dropIfExists('education_institutions');
    }
};
