<?php

namespace Semizzy\Addons\Education\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Semizzy\Addons\Education\Models\EducationInstitution;

final class EducationInstitutionImportService
{
    /**
     * Import discovered institutions without allowing a feed to activate or
     * overwrite an already-approved institution. New records require review.
     *
     * @return array{created:int,updated:int,skipped:int,review_required:int,rejected:int}
     */
    public function import(array $records, string $source = 'manual'): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'review_required' => 0, 'rejected' => 0];

        foreach ($records as $record) {
            $name = trim((string) ($record['name'] ?? ''));
            if ($name === '') {
                $counts['skipped']++;
                continue;
            }

            $externalId = trim((string) ($record['external_id'] ?? ''));
            $category = $this->normalizeCategory($record['category'] ?? $record['type'] ?? null);
            $state = trim((string) ($record['state'] ?? ''));
            // External identifiers are commonly scoped to a source; never merge across feeds on ID alone.
            $identity = $externalId !== '' ? 'external:' . Str::lower($source) . ':' . $externalId : 'name:' . Str::lower($name) . '|' . Str::lower($state) . '|' . $category;
            $sourceKey = substr($source . ':' . hash('sha256', $identity), 0, 180);
            // Feed-supplied codes are not trusted as identity: two unrelated sources can reuse one.
            // Generate a deterministic code from the institution's actual identity instead.\n            $code = $this->makeCode($name, $state, $category, $identity);
            $now = now();

            try {
                DB::transaction(function () use ($record, $name, $externalId, $category, $state, $source, $sourceKey, $code, $now, &$counts): void {
                $institution = null;
                if ($externalId !== '') {
                    $institution = EducationInstitution::withTrashed()->where('external_id', $externalId)->where('import_source', $source)->first();
                }
                if (!$institution) {
                    $institution = EducationInstitution::withTrashed()->where('source_key', $sourceKey)->first();
                }
                if (!$institution) {
                    $institution = EducationInstitution::withTrashed()->where('code', $code)->first();
                }

                if ($institution && $institution->review_status === 'rejected') {
                    $institution->forceFill(['last_synced_at' => $now])->save();
                    $counts['rejected']++;
                    return;
                }

                $payload = [
                    'code' => $code,
                    'source_key' => $sourceKey,
                    'name' => mb_substr($name, 0, 220),
                    'type' => $category,
                    'category' => $category,
                    'ownership' => $this->normalizeOwnership($record['ownership'] ?? null),
                    'accrediting_body' => $record['accrediting_body'] ?? null,
                    'established_year' => isset($record['established_year']) && (int) $record['established_year'] > 0
                        ? (int) $record['established_year'] : null,
                    'city' => $record['city'] ?? null,
                    'state' => $state !== '' ? mb_substr($state, 0, 80) : null,
                    'lga' => $record['lga'] ?? null,
                    'country' => $record['country'] ?? 'Nigeria',
                    'website' => $record['website'] ?? null,
                    'external_id' => $externalId !== '' ? mb_substr($externalId, 0, 120) : null,
                    'source_url' => $record['source_url'] ?? null,
                    'import_source' => $source,
                    'last_synced_at' => $now,
                    'source_hash' => hash('sha256', json_encode([
                        $name, $category, $state, $record['ownership'] ?? null,
                        $record['website'] ?? null, $record['source_url'] ?? null,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                    'metadata' => array_merge((array) ($record['metadata'] ?? []), ['last_import_source' => $source]),
                    'classification' => ['category' => $category, 'ownership' => $this->normalizeOwnership($record['ownership'] ?? null)],
                    'updated_at' => $now,
                ];

                if ($institution) {
                    if ($institution->trashed()) {
                        $institution->restore();
                    }
                    if ($institution->review_status === 'approved') {
                        // Sync may refresh provenance only; approved/admin-curated fields stay intact.
                        $institution->forceFill([
                            'last_synced_at' => $now,
                            'import_source' => $source,
                            'source_hash' => $payload['source_hash'],
                            'updated_at' => $now,
                        ])->save();
                        $counts['skipped']++;
                        return;
                    }

                    $institution->forceFill(array_merge($payload, [
                        'review_status' => 'pending',
                        'active' => false,
                    ]))->save();
                    $counts['updated']++;
                    $counts['review_required']++;
                    return;
                }

                EducationInstitution::create(array_merge($payload, [
                    'review_status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'active' => false,
                    'created_at' => $now,
                ]));
                $counts['created']++;
                $counts['review_required']++;
                });
            } catch (QueryException $exception) {
                // A concurrent import may win the unique-key race after our initial lookup.
                // Reconcile only when that exact identity now exists; unrelated SQL failures still surface.
                $raced = null;
                if ($externalId !== '') {
                    $raced = EducationInstitution::withTrashed()
                        ->where('external_id', $externalId)
                        ->where('import_source', $source)
                        ->first();
                }
                if (!$raced) {
                    $raced = EducationInstitution::withTrashed()->where('source_key', $sourceKey)->first();
                }
                if (!$raced) {
                    $raced = EducationInstitution::withTrashed()->where('code', $code)->first();
                }
                if (!$raced) {
                    throw $exception;
                }
                if ($raced->review_status === 'rejected') {
                    $counts['rejected']++;
                } else {
                    $counts['skipped']++;
                }
            }
        }

        return $counts;
    }

    private function makeCode(string $value, string $state, string $category, string $identity): string
    {
        $slug = Str::slug($value);
        if ($slug === '') {
            $slug = 'institution';
        }

        return substr($slug, 0, 65) . '-' . substr(hash('sha256', Str::lower($state) . '|' . $category . '|' . $identity), 0, 12);
    }

    private function normalizeCategory(mixed $value): string
    {
        $v = Str::lower(trim((string) $value));
        return match ($v) {
            'university', 'universities', 'federal_university', 'state_university', 'private_university' => 'university',
            'polytechnic', 'polytechnics' => 'polytechnic',
            'college', 'college of education', 'college_of_education', 'colleges_of_education' => 'college_of_education',
            'college of agriculture', 'college_of_agriculture' => 'college_of_agriculture',
            'college of health', 'college_of_health', 'college of health sciences and technology' => 'college_of_health',
            'college of nursing', 'college_of_nursing', 'nursing' => 'college_of_nursing',
            'monotechnic', 'specialised institution' => 'monotechnic',
            'innovation enterprise institution', 'innovation_enterprise', 'iei' => 'innovation_enterprise',
            'vocational enterprise institution', 'vocational_enterprise' => 'vocational_enterprise',
            'technical college', 'technical_college' => 'technical_college',
            'rtc', 'technical and vocational' => 'rtc',
            default => 'other',
        };
    }

    private function normalizeOwnership(mixed $value): ?string
    {
        return match (Str::lower(trim((string) $value))) {
            'federal', 'federal government' => 'federal',
            'state', 'state government' => 'state',
            'private' => 'private',
            'public' => 'public',
            'community' => 'community',
            'faith', 'faith based', 'faith-based' => 'faith_based',
            'other' => 'other',
            default => null,
        };
    }
}
