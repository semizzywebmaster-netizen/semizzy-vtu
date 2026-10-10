<?php

namespace Semizzy\Addons\Education\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Semizzy\Addons\Education\Models\EducationInstitutionSyncRun;

final class EducationInstitutionSyncRunService
{
    public function start(string $source, int $minimumExpectedRecords = 1): EducationInstitutionSyncRun
    {
        $source = trim($source);
        if ($source === '' || mb_strlen($source) > 100) {
            throw new DomainException('A source identifier of 1–100 characters is required.');
        }
        if ($minimumExpectedRecords < 1) {
            throw new DomainException('Minimum expected records must be at least one.');
        }

        return EducationInstitutionSyncRun::create([
            'source' => $source,
            'status' => 'running',
            'minimum_expected_records' => $minimumExpectedRecords,
            'started_at' => now(),
        ]);
    }

    /** Persist counters returned by the importer, not raw feed claims. */
    public function recordPage(
        EducationInstitutionSyncRun $run,
        array $counts,
        ?string $nextCursor = null,
        ?int $expectedTotal = null
    ): EducationInstitutionSyncRun {
        return DB::transaction(function () use ($run, $counts, $nextCursor, $expectedTotal): EducationInstitutionSyncRun {
            $locked = EducationInstitutionSyncRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertRunning($locked);

            if ($expectedTotal !== null && $expectedTotal < 0) {
                throw new DomainException('Expected total cannot be negative.');
            }
            foreach (['created', 'updated', 'skipped', 'rejected'] as $key) {
                if (!isset($counts[$key]) || !is_int($counts[$key]) || $counts[$key] < 0) {
                    throw new DomainException('Page counters must contain non-negative integers for created, updated, skipped and rejected.');
                }
            }

            $locked->pages_processed++;
            $locked->records_created += $counts['created'];
            $locked->records_updated += $counts['updated'];
            $locked->records_skipped += $counts['skipped'];
            $locked->records_rejected += $counts['rejected'];
            $locked->records_seen += array_sum($counts);
            $locked->next_cursor = $nextCursor !== null ? mb_substr($nextCursor, 0, 500) : null;
            if ($expectedTotal !== null) {
                $locked->expected_total = $expectedTotal;
            }
            $locked->save();

            return $locked;
        });
    }

    public function complete(EducationInstitutionSyncRun $run): EducationInstitutionSyncRun
    {
        return DB::transaction(function () use ($run): EducationInstitutionSyncRun {
            $locked = EducationInstitutionSyncRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertRunning($locked);

            if ($locked->next_cursor !== null) {
                throw new DomainException('Cannot complete an import while a next-page cursor remains.');
            }
            if ($locked->records_seen < $locked->minimum_expected_records) {
                $locked->status = 'incomplete';
                $locked->error_summary = 'Observed record count was below the configured minimum; source may be partial or unavailable.';
                $locked->finished_at = now();
                $locked->save();

                return $locked;
            }
            if ($locked->expected_total !== null && $locked->records_seen !== $locked->expected_total) {
                $locked->status = 'incomplete';
                $locked->error_summary = 'Observed record count did not match the source-reported total; pagination may be incomplete.';
                $locked->finished_at = now();
                $locked->save();

                return $locked;
            }

            $locked->status = 'completed';
            $locked->error_summary = null;
            $locked->finished_at = now();
            $locked->save();

            return $locked;
        });
    }

    public function fail(EducationInstitutionSyncRun $run, string $safeSummary): EducationInstitutionSyncRun
    {
        $safeSummary = trim($safeSummary);
        if ($safeSummary === '') {
            $safeSummary = 'Synchronization failed; inspect protected server logs for details.';
        }
        $safeSummary = preg_replace('~https?://\S+~i', '[redacted-url]', $safeSummary) ?? 'Synchronization failed.';
        $safeSummary = preg_replace('/(?:token|secret|password|api[_-]?key)\s*[:=]\s*[^\s,;]+/i', '[redacted-credential]', $safeSummary) ?? 'Synchronization failed.';
        $safeSummary = mb_substr($safeSummary, 0, 500);

        return DB::transaction(function () use ($run, $safeSummary): EducationInstitutionSyncRun {
            $locked = EducationInstitutionSyncRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->assertRunning($locked);
            $locked->status = 'failed';
            $locked->error_summary = $safeSummary;
            $locked->finished_at = now();
            $locked->save();

            return $locked;
        });
    }

    private function assertRunning(EducationInstitutionSyncRun $run): void
    {
        if ($run->status !== 'running') {
            throw new DomainException('Only a running synchronization can be changed.');
        }
    }
}
