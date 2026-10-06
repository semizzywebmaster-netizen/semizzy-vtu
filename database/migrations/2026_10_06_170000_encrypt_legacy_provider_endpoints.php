<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function encryptValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return Crypt::encrypt(serialize($value));
    }

    private function decryptValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            $decoded = unserialize(Crypt::decrypt($value));
            return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function up(): void
    {
        Schema::table('api_providers', function (Blueprint $table) {
            $table->text('endpoints')->nullable()->change();
        });

        foreach (DB::table('api_providers')->select('id', 'endpoints')->orderBy('id')->cursor() as $row) {
            DB::table('api_providers')->where('id', $row->id)->update([
                'endpoints' => $this->encryptValue($row->endpoints),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('api_providers')->select('id', 'endpoints')->orderBy('id')->cursor() as $row) {
            DB::table('api_providers')->where('id', $row->id)->update([
                'endpoints' => $this->decryptValue($row->endpoints),
            ]);
        }

        Schema::table('api_providers', function (Blueprint $table) {
            $table->json('endpoints')->nullable()->change();
        });
    }
};
