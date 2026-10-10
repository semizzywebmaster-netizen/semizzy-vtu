<?php

namespace Semizzy\Addons\Education\Services;

use DomainException;
use Illuminate\Support\Facades\Http;
use Semizzy\Addons\Education\Models\EducationInstitutionSyncRun;
use Throwable;

final class NcceAccreditedCollegesAdapter
{
    public const SOURCE = 'ncce-accredited-colleges';
    public const URL = 'https://ncce.gov.ng/AccreditedColleges/Index';
    private const MINIMUM_EXPECTED_RECORDS = 200;

    public function __construct(
        private readonly EducationInstitutionImportService $importer,
        private readonly EducationInstitutionSyncRunService $runs,
    ) {}

    /**
     * Fetch the official, server-rendered NCCE directory. This is HTML, not a JSON API.
     * No credentials, query parameters, or undocumented endpoints are used.
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
                throw new DomainException('NCCE directory returned an unsuccessful HTTP response.');
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
                throw new DomainException('NCCE directory did not return an HTML document.');
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
            $this->runs->fail($run, 'NCCE directory sync failed: ' . $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * @return array{records: array<int, array<string, mixed>>, expected_total: int}
     */
    public function parse(string $html): array
    {
        if (trim($html) === '' || !class_exists(\DOMDocument::class)) {
            throw new DomainException('NCCE directory response is empty or the DOM extension is unavailable.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            throw new DomainException('NCCE directory HTML could not be parsed.');
        }

        $xpath = new \DOMXPath($document);
        $targetTable = null;
        foreach ($xpath->query('//table') ?: [] as $table) {
            $headers = [];
            foreach ($xpath->query('.//thead//th | .//tr[1]//th', $table) ?: [] as $header) {
                $headers[] = $this->normalizeText($header->textContent);
            }
            $joined = strtolower(implode('|', $headers));
            if (str_contains($joined, 's/n') && str_contains($joined, 'name')
                && str_contains($joined, 'state') && str_contains($joined, 'website')
                && (str_contains($joined, 'ownership') || str_contains($joined, 'type'))) {
                $targetTable = $table;
                break;
            }
        }

        if (!$targetTable) {
            throw new DomainException('NCCE directory table headers changed; refusing to import an unrecognised layout.');
        }

        $records = [];
        $serials = [];
        foreach ($xpath->query('.//tbody/tr | .//tr[td]', $targetTable) ?: [] as $row) {
            $cells = $xpath->query('./td', $row);
            if (!$cells || $cells->length < 6) {
                continue;
            }

            $serialText = $this->normalizeText($cells->item(0)->textContent);
            if (!preg_match('/^\d+$/', $serialText)) {
                continue;
            }
            $serial = (int) $serialText;
            $name = preg_replace('/\s+OPEN$/i', '', $this->normalizeText($cells->item(1)->textContent)) ?? '';
            if ($serial < 1 || $name === '') {
                throw new DomainException('NCCE directory contains an invalid serial number or blank institution name.');
            }

            $ownershipLabel = $this->normalizeText($cells->item(3)->textContent);
            $state = $this->normalizeText($cells->item(4)->textContent);
            $website = $this->websiteFromCell($cells->item(5));
            $category = str_contains(strtolower($ownershipLabel), 'polytechnic') ? 'polytechnic' : 'college_of_education';
            $ownership = match (true) {
                str_contains(strtolower($ownershipLabel), 'private') => 'private',
                str_contains(strtolower($ownershipLabel), 'state') => 'state',
                str_contains(strtolower($ownershipLabel), 'federal') => 'federal',
                default => 'other',
            };

            $records[] = [
                'name' => $name,
                'category' => $category,
                'ownership' => $ownership,
                'state' => $state !== '' ? $state : null,
                'country' => 'Nigeria',
                'website' => $website,
                'external_id' => 'ncce:' . hash('sha256', mb_strtolower($name)),
                'source_url' => self::URL,
                'metadata' => [
                    'official_source' => 'National Commission for Colleges of Education',
                    'source_serial' => $serial,
                    'ownership_label' => $ownershipLabel,
                ],
            ];
            $serials[] = $serial;
        }

        if (count($records) < self::MINIMUM_EXPECTED_RECORDS) {
            throw new DomainException('NCCE directory returned fewer than the safety minimum; refusing a partial import.');
        }

        $expected = max($serials);
        $sorted = $serials;
        sort($sorted, SORT_NUMERIC);
        if ($sorted !== range(1, $expected) || count(array_unique(array_map('mb_strtolower', array_column($records, 'name')))) !== count($records)) {
            throw new DomainException('NCCE directory serials are incomplete or institution names are duplicated; refusing to mark the source complete.');
        }

        return ['records' => $records, 'expected_total' => $expected];
    }

    private function websiteFromCell(\DOMNode $cell): ?string
    {
        $xpath = new \DOMXPath($cell->ownerDocument);
        $link = $xpath->query('.//a[@href]', $cell)?->item(0);
        $href = $link instanceof \DOMElement ? trim($link->getAttribute('href')) : '';
        if ($href === '' || in_array(strtolower($href), ['#', '-', 'null', 'javascript:void(0)'], true)) {
            return null;
        }

        if (!preg_match('~^https?://~i', $href)) {
            $href = 'https://' . ltrim($href, '/');
        }
        $parts = parse_url($href);
        if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || !str_contains($parts['host'], '.')) {
            return null;
        }

        return mb_substr($href, 0, 255);
    }

    private function normalizeText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
