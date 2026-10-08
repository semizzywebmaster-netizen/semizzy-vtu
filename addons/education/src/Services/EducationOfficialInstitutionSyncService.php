<?php
namespace App\Addons\Education\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EducationOfficialInstitutionSyncService
{
    public function syncAll(): array
    {
        $results = [];
        foreach ((array) config('education.institution_sources.official_sources', []) as $source) {
            $key = $source['key'] ?? null;
            if (!$key) {
                continue;
            }

            $results[$key] = match ($key) {
                'nuc_universities' => $this->syncNucUniversities($source),
                default => [
                    'status' => 'manual_parser_required',
                    'source' => $source['url'] ?? null,
                    'message' => 'Official source registered; parser is intentionally not guessing its table structure.',
                ],
            };
        }

        return $results;
    }

    public function syncNucUniversities(array $source): array
    {
        $response = Http::timeout(30)->retry(2, 500)->get($source['url']);
        if (!$response->successful()) {
            throw new RuntimeException('NUC institution catalogue could not be retrieved.');
        }

        $html = $response->body();
        preg_match_all('/<tr[^>]*>\\s*<td[^>]*>\\s*(\\d+)\\s*<\\/td>\\s*<td[^>]*>(.*?)<\\/td>\\s*<td[^>]*>(\\d{4})<\\/td>\\s*<td[^>]*>(.*?)<\\/td>\\s*<td[^>]*>(.*?)<\\/td>/is', $html, $matches, PREG_SET_ORDER);

        $records = [];
        foreach ($matches as $match) {
            $name = trim(html_entity_decode(strip_tags($match[2])));
            $year = (int) $match[3];
            $ownership = $this->normalizeOwnership(strip_tags($match[4]));
            $state = trim(html_entity_decode(strip_tags($match[5])));

            if ($name === '') {
                continue;
            }

            $records[] = [
                'name' => preg_replace('/\\s+/', ' ', $name),
                'category' => 'university',
                'ownership' => $ownership,
                'state' => $state,
                'established_year' => $year > 0 ? $year : null,
                'accrediting_body' => 'NUC',
                'metadata' => [
                    'official_source' => $source['url'],
                    'official_row' => (int) $match[1],
                ],
            ];
        }

        $import = app(EducationInstitutionImportService::class)->import($records, 'NUC-NUS');
        return [
            'status' => 'imported',
            'source' => $source['url'],
            'records_found' => count($records),
            'import' => $import,
        ];
    }

    private function normalizeOwnership(string $value): string
    {
        return match (strtolower(trim($value))) {
            'federal' => 'federal',
            'state' => 'state',
            'private' => 'private',
            default => 'other',
        };
    }
}
