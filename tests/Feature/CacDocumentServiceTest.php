<?php

namespace Tests\Feature;

use App\Models\CacOrder;
use App\Models\User;
use App\Services\Cac\CacDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CacDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_private_document_is_stored_with_checksum(): void
    {
        $user = User::factory()->create();
        $order = CacOrder::create([
            'uuid'=>(string)\Illuminate\Support\Str::uuid(),
            'reference'=>'CAC-TEST-'.\Illuminate\Support\Str::random(8),
            'user_id'=>$user->id,
            'service_type'=>'business_name_registration',
            'status'=>'pending_review',
            'idempotency_key'=>'test-'.\Illuminate\Support\Str::uuid(),
            'amount_minor'=>1000,'fee_minor'=>0,'total_minor'=>1000,'currency'=>'NGN',
        ]);
        $file = UploadedFile::fake()->createWithContent('identity.pdf', '%PDF-1.4 test');

        $doc = app(CacDocumentService::class)->upload($order, $file, 'identity', $user->id);

        $this->assertSame('uploaded', $doc->status);
        $this->assertNotEmpty($doc->checksum);
        $this->assertStringStartsWith('private/cac/', $doc->storage_path);
    }
}
