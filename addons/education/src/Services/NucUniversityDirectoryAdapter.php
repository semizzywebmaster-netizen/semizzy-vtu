<?php

namespace Semizzy\\Addons\\Education\\Services;

use DomainException;
use Illuminate\\Support\\Facades\\Http;
use Semizzy\\Addons\\Education\\Models\\EducationInstitutionSyncRun;
use Throwable;

final class NucUniversityDirectoryAdapter
{
    public const SOURCE = 'nuc-university-system';
    public const URL = 'https://enuc.nuc.edu.ng/nus';

    // The current official directory snapshot is numbered 1–328; fail closed if it is truncated.
    private const MINIMUM_EXPECTED_RECORDS = 328;

    public function __construct(
        private readonly EducationInstitutionImportService $importer,
        private readonly EducationInstitutionSyncRunService $runs,
    ) {}

    /**
     * Fetch the official NUC university directory. It is server-rendered HTML,
     * not a documented JSON API. No private or undocumented endpoint is used.
     */
    public function sync(): EducationInstitutionSyncRun
    {
        $run = $this->runs->start(self::SOURCE, self::MINIMUM_EXPECTED_RECORDS);

        try {
            $response = Http::accept('text/html')
                ->withUserAgent('SEMIZZY-ONE-EducationDirectorySync/1.0 (+official-directory-import)')
                ->connectTimeout(5)
                ->timeout(20)
                ->get(self::URL);

            if (!$response->successful()) {
                throw new DomainException('NUC university directory returned an unsuccessful HTTP response.');
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
                throw new DomainException('NUC university directory did not return an HTML document.');
            }

            $feed = $this->parse($response->body());
            $counts = $this->importer->import($feed['records'], self::SOURCE);
            $run = $this->runs->recordPage($run, [
                'created' => $counts['created'],
                'updated' => $counts['updated'],
                'skipped' => $counts['skipped'],
                'rejected' => $counts['rejected'],
            ], null, $feed['expected_total']);

            return $this->runs->complete($run);
        } catch (Throwable $exception) {
            $this->runs->fail($run, 'NUC university directory sync failed: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * @return array{records: array<int, array<string, mixed>>, expected_total: int}
     */
    public function parse(string $html): array
    {
        if (trim($html) === '' || !class_exists(\\DOMDocument::class)) {
            throw new DomainException('NUC directory response is empty or the DOM extension is unavailable.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \\DOMDocument();
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            throw new DomainException('NUC directory HTML could not be parsed.');
        }

        $xpath = new \\DOMXPath($document);
        $targetTable = null;
        foreach ($xpath->query('//table') ?: [] as $table) {
            $headers = [];
            foreach ($xpath->query('.//thead//th | .//tr[1]//th', $table) ?: [] as $header) {
                $headers[] = strtolower($this->normalizeText($header->textContent));
            }
            $joined = implode('|', $headers);
            if ((str_contains($joined, '#') || str_contains($joined, 's/n'))
                && str_contains($joined, 'university name')
                && str_contains($joined, 'year established')
                && str_contains($joined, 'ownership')
                && str_contains($joined, 'state')) {
                $targetTable = $table;
                break;
            }
        }

        if (!$targetTable) {
            throw new DomainException('NUC directory table headers changed; refusing to import an unrecognised layout.');
        }

        $records = [];
        $serials = [];
        $ownershipCounts = ['federal' => 0, 'state' => 0, 'private' => 0];
        foreach ($xpath->query('.//tbody/tr | .//tr[td]', $targetTable) ?: [] as $row) {
            $cells = $xpath->query('./td', $row);
            if (!$cells || $cells->length < 5) {
                continue;
            }

            $serialText = preg_replace('/[.\\s]+$/u', '', $this->normalizeText($cells->item(0)->textContent)) ?? '';
            if (!preg_match('/^\\d+$/', $serialText)) {
                continue;
            }

            $serial = (int) $serialText;
            $name = $this->normalizeText($cells->item(1)->textContent);
            $yearText = $this->normalizeText($cells->item(2)->textContent);
            $ownershipLabel = $this->normalizeText($cells->item(3)->textContent);
            $state = $this->normalizeText($cells->item(4)->textContent);
            $ownership = match (strtolower($ownershipLabel)) {
                'federal' => 'federal',
                'state' => 'state',
                'private' => 'private',
                default => null,
            };

            if ($serial < 1 || $name === '' || $ownership === null || $state === '') {
                throw new DomainException('NUC directory contains an invalid serial, name, ownership or state; refusing import.');
            }
            if (!preg_match('/^\\d{4}$/', $yearText)) {
                throw new DomainException('NUC directory contains an invalid establishment year; refusing import.');
            }

            $ownershipCounts[$ownership]++;
            $records[] = [
                'name' => $name,
                'category' => 'university',
                'ownership' => $ownership,
                'state' => $state,
                'country' => 'Nigeria',
                'established_year' => (int) $yearText,
                'external_id' => 'nuc:' . hash('sha256', mb_strtolower($name . '|' . $state . '|' . $ownership)),
                'source_url' => self::URL,
                'metadata' => [
                    'official_source' => 'National Universities Commission — Nigerian University System',
                    'source_serial' => $serial,
                    'ownership_label' => $ownershipLabel,
                ],
            ];
            $serials[] = $serial;
        }

        if (count($records) < self::MINIMUM_EXPECTED_RECORDS) {
            throw new DomainException('NUC directory returned fewer than the safety minimum; refusing a partial import.');
        }

        $expected = max($serials);
        $sorted = $serials;
        sort($sorted, SORT_NUMERIC);
        if ($sorted !== range(1, $expected)) {
            throw new DomainException('NUC directory serials are missing or duplicated; refusing to mark the source complete.');
        }

        $names = array_map(static fn (array $record): string => mb_strtolower($record['name']), $records);
        if (count(array_unique($names)) !== count($records)) {
            throw new DomainException('NUC directory contains duplicate institution names; refusing to mark the source complete.');
        }

        $pageText = $this->normalizeText($document->textContent);
        $publishedCounts = [
            'federal' => $this->summaryCount($pageText, '/\\b(\\d+)\\s+Federal Universities\\b/i'),
            'state' => $this->summaryCount($pageText, '/\\b(\\d+)\\s+State Universities\\b/i'),
            'private' => $this->summaryCount($pageText, '/\\b(\\d+)\\s+Private Universities\\b/i'),
        ];
        if (in_array(null, $publishedCounts, true) || $publishedCounts !== $ownershipCounts) {
            throw new DomainException('NUC directory summary counts do not match parsed ownership totals; refusing an incomplete or changed source.');
        }

        return ['records' => $records, 'expected_total' => $expected];
    }

    private function summaryCount(string $text, string $pattern): ?int
    {
        return preg_match($pattern, $text, $matches) === 1 ? (int) $matches[1] : null;
    }

    private function normalizeText(string $value): string
    {
        return trim(preg_replace('/\\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
