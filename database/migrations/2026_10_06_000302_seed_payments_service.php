<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('services') || ! DB::getSchemaBuilder()->hasTable('service_categories')) {
            return;
        }

        $categoryId = DB::table('service_categories')->where('key', 'payments')->value('id');
        if (! $categoryId) {
            $categoryId = DB::table('service_categories')->insertGetId([
                'key' => 'payments',
                'name' => 'Payments',
                'description' => 'Payment collection and wallet funding services.',
                'enabled' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('services')->updateOrInsert(
            ['key' => 'payments.gateway'],
            [
                'category_id' => $categoryId,
                'name' => 'Payment Gateway',
                'description' => 'Generic provider-backed payment initiation and status service.',
                'enabled' => true,
                'metadata' => json_encode(['addon' => 'payments.gateway']),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('services')) return;
        DB::table('services')->where('key', 'payments.gateway')->delete();
        if (DB::getSchemaBuilder()->hasTable('service_categories')) {
            DB::table('service_categories')->where('key', 'payments')->delete();
        }
    }
};
