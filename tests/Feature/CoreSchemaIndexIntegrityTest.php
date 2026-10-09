<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoreSchemaIndexIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_device_revocation_index_is_created_exactly_once(): void
    {
        $indexes = DB::select("PRAGMA index_list('user_devices')");
        $matches = array_values(array_filter(
            $indexes,
            static fn (object $index): bool => ($index->name ?? null) === 'user_devices_user_id_revoked_at_index'
        ));

        $this->assertCount(1, $matches);
    }
}
