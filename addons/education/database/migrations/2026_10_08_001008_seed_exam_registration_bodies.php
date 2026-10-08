<?php
use App\Addons\Education\Services\EducationExamBodyImportService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $bodies = (array) config('education.exam_registration_bodies.bodies', []);
        if ($bodies !== []) {
            app(EducationExamBodyImportService::class)->import($bodies, 'education.exam_registration_bodies');
        }
    }

    public function down(): void
    {
        $codes = collect((array) config('education.exam_registration_bodies.bodies', []))
            ->pluck('code')->filter()->values()->all();

        if ($codes !== []) {
            \App\Addons\Education\Models\EducationExamBody::whereIn('code', $codes)->delete();
        }
    }
};
