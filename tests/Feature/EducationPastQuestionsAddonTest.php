<?php
namespace Tests\Feature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Semizzy\Addons\Education\Models\EducationLibraryItem;
use Tests\TestCase;
class EducationPastQuestionsAddonTest extends TestCase {
 private bool $createdUsersTable=false;
 protected function setUp():void {
  parent::setUp();
  if(!Schema::hasTable('users')) {
   Schema::create('users',function(Blueprint $t){$t->id();$t->string('name')->nullable();$t->string('email')->nullable();$t->string('password')->nullable();$t->timestamps();});
   $this->createdUsersTable=true;
  }
  Schema::dropIfExists('education_library_purchases');
  Schema::dropIfExists('education_library_items');
  $migration=require base_path('addons/education/database/migrations/2026_10_09_110000_create_education_past_question_library.php');
  $migration->up();
 }
 protected function tearDown():void {
  Schema::dropIfExists('education_library_purchases');
  Schema::dropIfExists('education_library_items');
  if($this->createdUsersTable)Schema::dropIfExists('users');
  parent::tearDown();
 }
 public function test_manifest_registers_both_past_question_sections_and_permissions():void {
  $manifest=require base_path('addons/education/manifest.php');
  $this->assertSame('education',$manifest['identifier']);
  $this->assertContains('education.view',$manifest['permissions']);
  $this->assertContains('education.purchase',$manifest['permissions']);
  $this->assertContains('addons/education/routes/web.php',$manifest['web_route_files']);
  $labels=array_column($manifest['navigation'],'label');
  $this->assertContains('School Past Questions',$labels);
  $this->assertContains('Exam Past Questions',$labels);
 }
 public function test_library_schema_supports_both_catalogue_categories_and_private_file_metadata():void {
  $this->assertTrue(Schema::hasTable('education_library_items'));
  $this->assertTrue(Schema::hasTable('education_library_purchases'));
  foreach(['category','institution','course_code','academic_session','exam_body','subject','exam_year','file_path','is_free','price_minor','status','published_at'] as $column)$this->assertTrue(Schema::hasColumn('education_library_items',$column),$column);
  $school=EducationLibraryItem::create(['category'=>'school_past_question','title'=>'Introductory Biology','slug'=>'introductory-biology','institution'=>'Example University','course_code'=>'BIO101','file_path'=>'education/past-questions/private.pdf','file_name'=>'biology.pdf','mime_type'=>'application/pdf','file_size'=>1000,'is_free'=>true,'price_minor'=>0,'currency'=>'NGN','status'=>'published','published_at'=>now()]);
  EducationLibraryItem::create(['category'=>'exam_past_question','title'=>'WAEC Mathematics 2024','slug'=>'waec-mathematics-2024','exam_body'=>'WAEC','subject'=>'Mathematics','exam_year'=>2024,'file_path'=>'education/past-questions/waec.pdf','file_name'=>'waec.pdf','mime_type'=>'application/pdf','file_size'=>1000,'is_free'=>false,'price_minor'=>50000,'currency'=>'NGN','status'=>'published','published_at'=>now()]);
  $this->assertSame(1,EducationLibraryItem::published()->category('school_past_question')->count());
  $this->assertSame(1,EducationLibraryItem::published()->category('exam_past_question')->count());
  $this->assertSame('school_past_question',$school->fresh()->category);
 }
}
