<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemMaintenanceAndServiceIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_clear_application_cache(): void
    {
        $admin=$this->admin();
        $this->actingAs($admin)->post('/admin/maintenance/cache-clear')->assertRedirect()->assertSessionHas('success');
    }

    public function test_admin_can_generate_service_icon_assignments(): void
    {
        $admin=$this->admin();
        $category=ServiceCategory::create(['key'=>'digital','name'=>'Digital','enabled'=>true,'sort_order'=>1]);
        $service=Service::create(['category_id'=>$category->id,'key'=>'airtime','name'=>'Airtime','enabled'=>true,'metadata'=>[]]);

        $this->actingAs($admin)->post('/admin/catalogue/services/generate-icons')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('airtime',$service->fresh()->metadata['icon']);
    }

    public function test_admin_can_import_safe_svg_service_icon(): void
    {
        Storage::fake('public');
        $admin=$this->admin();
        $category=ServiceCategory::create(['key'=>'digital','name'=>'Digital','enabled'=>true,'sort_order'=>1]);
        $service=Service::create(['category_id'=>$category->id,'key'=>'data','name'=>'Data','enabled'=>true,'metadata'=>[]]);

        $file=UploadedFile::fake()->createWithContent('data.svg','<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg>');
        $this->actingAs($admin)->post('/admin/catalogue/services/'.$service->id.'/icon',['icon'=>$file])->assertRedirect()->assertSessionHas('success');
        $url=$service->fresh()->metadata['icon_url'];
        $this->assertStringContainsString('/storage/service-icons/',$url);
        Storage::disk('public')->assertExists(str_replace('/storage/','',$url));
    }

    public function test_admin_can_create_a_backup_archive_when_zip_is_available(): void
    {
        if (!class_exists(\ZipArchive::class)) $this->markTestSkipped('ZIP extension unavailable.');
        $admin=$this->admin();
        $response=$this->actingAs($admin)->get('/admin/maintenance/backup');
        $response->assertDownload();
        $this->assertDirectoryExists(storage_path('app/backups'));
    }

    private function admin(): User
    {
        $user=User::create(['name'=>'Maintenance Admin','email'=>fake()->unique()->safeEmail(),'password'=>fake()->password(20,30,true,true,true),'role'=>'ADMIN','status'=>'active','tier'=>5]);
        $user->forceFill(['email_verified_at'=>now()])->save();
        return $user;
    }
}
