<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacy = DB::table('addons')
                ->where('identifier', 'vtu')
                ->lockForUpdate()
                ->first();

            $currentExists = DB::table('addons')
                ->where('identifier', 'vtu.digital-services')
                ->exists();

            // Preserve an already-correct registration. Never create a duplicate.
            if (! $legacy || $currentExists) {
                return;
            }

            $manifest = $legacy->manifest !== null
                ? json_decode($legacy->manifest, true)
                : null;

            if (is_array($manifest)) {
                $manifest['identifier'] = 'vtu.digital-services';
            }

            DB::table('addons')
                ->where('id', $legacy->id)
                ->update([
                    'identifier' => 'vtu.digital-services',
                    'manifest' => is_array($manifest) ? json_encode($manifest, JSON_UNESCAPED_SLASHES) : $legacy->manifest,
                    'updated_at' => now(),
                ]);

            DB::table('addon_lifecycle_events')
                ->where('addon_id', $legacy->id)
                ->update([
                    'addon_identifier' => 'vtu.digital-services',
                ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $current = DB::table('addons')
                ->where('identifier', 'vtu.digital-services')
                ->lockForUpdate()
                ->first();

            $legacyExists = DB::table('addons')
                ->where('identifier', 'vtu')
                ->exists();

            if (! $current || $legacyExists) {
                return;
            }

            $manifest = $current->manifest !== null
                ? json_decode($current->manifest, true)
                : null;

            if (is_array($manifest)) {
                $manifest['identifier'] = 'vtu';
            }

            DB::table('addons')
                ->where('id', $current->id)
                ->update([
                    'identifier' => 'vtu',
                    'manifest' => is_array($manifest) ? json_encode($manifest, JSON_UNESCAPED_SLASHES) : $current->manifest,
                    'updated_at' => now(),
                ]);

            DB::table('addon_lifecycle_events')
                ->where('addon_id', $current->id)
                ->update([
                    'addon_identifier' => 'vtu',
                ]);
        });
    }
};
