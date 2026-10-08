<?php
namespace App\Addons\Education\Services;

use App\Addons\Education\Models\EducationExamBody;
use Illuminate\Support\Str;

class EducationExamBodyImportService
{
    public function import(array $records, string $source = 'config'): array
    {
        $created = $updated = $skipped = 0;

        foreach ($records as $record) {
            $name = trim((string) ($record['name'] ?? ''));
            $code = Str::lower(trim((string) ($record['code'] ?? '')));
            if ($name === '' || $code === '') {
                $skipped++;
                continue;
            }

            $payload = [
                'code' => $code,
                'name' => $name,
                'short_name' => $record['short_name'] ?? null,
                'country' => $record['country'] ?? 'NG',
                'type' => $record['type'] ?? 'other',
                'description' => $record['description'] ?? null,
                'official_website' => $record['official_website'] ?? null,
                'registration_url' => $record['registration_url'] ?? null,
                'programmes' => $record['programmes'] ?? [],
                'services' => $record['services'] ?? [],
                'metadata' => array_merge((array) ($record['metadata'] ?? []), [
                    'import_source' => $source,
                    'admin_extensible' => true,
                ]),
                'active' => array_key_exists('active', $record) ? (bool) $record['active'] : true,
            ];

            $model = EducationExamBody::withTrashed()->where('code', $code)->first();

            if ($model) {
                if ($model->trashed()) {
                    $model->restore();
                }
                // Preserve admin-maintained fields when an official catalogue
                // refresh does not provide a replacement value.
                foreach (['description','official_website','registration_url'] as $field) {
                    if (($payload[$field] ?? null) === null && $model->{$field}) {
                        $payload[$field] = $model->{$field};
                    }
                }
                $model->fill($payload)->save();
                $updated++;
            } else {
                EducationExamBody::create($payload);
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }
}
