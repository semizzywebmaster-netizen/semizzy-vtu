<?php
namespace Semizzy\Addons\Education\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class EducationReferenceImportController extends Controller
{
    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $stream = fopen($request->file('file')->getRealPath(), 'r');
        if ($stream === false) {
            return back()->withErrors(['file' => 'The uploaded CSV could not be read.']);
        }

        $headers = fgetcsv($stream);
        if (!is_array($headers)) {
            fclose($stream);
            return back()->withErrors(['file' => 'The CSV is empty.']);
        }

        $headers[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) $headers[0]);
        $headers = array_map(static fn ($header) => Str::of((string) $header)->trim()->lower()->replace(' ', '_')->toString(), $headers);
        if (count($headers) !== count(array_unique($headers))) {
            fclose($stream);
            return back()->withErrors(['file' => 'CSV column names must be unique.']);
        }
        $required = ['kind', 'name', 'category'];
        if (array_diff($required, $headers)) {
            fclose($stream);
            return back()->withErrors(['file' => 'CSV headers must include kind,name,category. Optional columns: short_name,state,country,official_url,source_url.']);
        }

        $allowed = ['kind', 'name', 'category', 'short_name', 'state', 'country', 'official_url', 'source_url'];
        $headerIndex = array_flip($headers);
        $created = 0;
        $duplicates = 0;
        $invalid = 0;
        $line = 1;
        $now = now();

        DB::transaction(function () use ($stream, $headerIndex, $allowed, $request, $now, &$created, &$duplicates, &$invalid, &$line) {
            while (($values = fgetcsv($stream)) !== false) {
                $line++;
                if ($line > 2001) {
                    $invalid++;
                    break;
                }
                if (count(array_filter($values, static fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $row = [];
                foreach ($allowed as $column) {
                    $row[$column] = isset($headerIndex[$column]) ? trim((string) ($values[$headerIndex[$column]] ?? '')) : '';
                }
                if (!in_array($row['kind'], ['school', 'exam_body', 'exam_type'], true)
                    || $row['name'] === '' || mb_strlen($row['name']) > 220
                    || ($row['kind'] === 'school' && $row['category'] === '')
                    || mb_strlen($row['category']) > 80
                    || ($row['official_url'] !== '' && !filter_var($row['official_url'], FILTER_VALIDATE_URL))
                    || ($row['source_url'] !== '' && !filter_var($row['source_url'], FILTER_VALIDATE_URL))) {
                    $invalid++;
                    continue;
                }

                $category = $row['category'] !== '' ? $row['category'] : null;
                $key = hash('sha256', $row['kind'].'|'.($category ?? '').'|'.$row['name']);
                if (DB::table('education_reference_catalogue')->where('catalogue_key', $key)->exists()) {
                    $duplicates++;
                    continue;
                }

                DB::table('education_reference_catalogue')->insert([
                    'kind' => $row['kind'],
                    'category' => $category,
                    'name' => $row['name'],
                    'catalogue_key' => $key,
                    'short_name' => $row['short_name'] !== '' ? $row['short_name'] : null,
                    'state' => $row['state'] !== '' ? $row['state'] : null,
                    'country' => $row['country'] !== '' ? $row['country'] : ($row['kind'] === 'school' ? 'Nigeria' : null),
                    'official_url' => $row['official_url'] !== '' ? $row['official_url'] : null,
                    'source_url' => $row['source_url'] !== '' ? $row['source_url'] : null,
                    'metadata' => json_encode(['catalogue_source' => 'admin_csv_import', 'imported_by' => $request->user()->id]),
                    'is_active' => false,
                    'review_status' => 'pending',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $created++;
            }
        });

        fclose($stream);
        $message = "CSV import finished: {$created} added to pending review, {$duplicates} duplicates skipped, {$invalid} invalid rows skipped.";
        return back()->with('success', $message);
    }
}
