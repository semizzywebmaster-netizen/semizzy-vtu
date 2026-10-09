<?php
namespace Tests\Feature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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
  Schema::dropIfExists('education_reference_categories');
  Schema::dropIfExists('education_reference_catalogue');
  $referenceMigration=require base_path('addons/education/database/migrations/2026_10_09_110100_create_and_import_education_reference_catalogue.php');
  $referenceMigration->up();
  $adminReferenceMigration=require base_path('addons/education/database/migrations/2026_10_09_110200_add_admin_managed_education_reference_fields.php');
  $adminReferenceMigration->up();
  $migration=require base_path('addons/education/database/migrations/2026_10_09_110000_create_education_past_question_library.php');
  $migration->up();
 }
 protected function tearDown():void {
  Schema::dropIfExists('education_library_purchases');
  Schema::dropIfExists('education_library_items');
  Schema::dropIfExists('education_reference_categories');
  Schema::dropIfExists('education_reference_catalogue');
  if($this->createdUsersTable)Schema::dropIfExists('users');
  parent::tearDown();
 }
 public function test_manifest_registers_both_past_question_sections_and_permissions():void {
  $manifest=require base_path('addons/education/manifest.php');
  $this->assertSame('education',$manifest['identifier']);
  $this->assertContains('education.view',$manifest['permissions']);
  $this->assertContains('education.purchase',$manifest['permissions']);
  $this->assertContains('addons/education/routes/web.php',$manifest['web_route_files']);
  $this->assertContains('2026_10_09_110200_add_admin_managed_education_reference_fields.php',$manifest['migrations']);
  $this->assertSame('/admin/education/references',$manifest['admin_navigation'][1]['url']);
  $labels=array_column($manifest['navigation'],'label');
  $this->assertContains('School Past Questions',$labels);
  $this->assertContains('Exam Past Questions',$labels);
 }
 public function test_reference_catalogue_imports_exam_bodies_exam_types_and_categorised_schools():void {
  $this->assertTrue(Schema::hasTable('education_reference_catalogue'));
  $this->assertTrue(Schema::hasTable('education_reference_categories'));
  $this->assertTrue(Schema::hasColumn('education_reference_catalogue','created_by'));
  $this->assertGreaterThanOrEqual(20,DB::table('education_reference_catalogue')->where('kind','exam_body')->count());
  $this->assertGreaterThanOrEqual(20,DB::table('education_reference_catalogue')->where('kind','exam_type')->count());
  $this->assertGreaterThanOrEqual(50,DB::table('education_reference_catalogue')->where('kind','school')->count());
  $this->assertGreaterThanOrEqual(3,DB::table('education_reference_catalogue')->where('kind','school')->distinct()->count('category'));
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'exam_body','short_name'=>'WAEC']);
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'school','name'=>'University of Lagos','category'=>'federal_university']);
 }
 public function test_reference_catalogue_has_room_for_admin_added_schools_and_exam_bodies():void {
  DB::table('education_reference_catalogue')->insert([
   'kind'=>'school','category'=>'private_university','name'=>'Admin Added University','catalogue_key'=>hash('sha256','school|private_university|Admin Added University'),
   'short_name'=>null,'state'=>'Lagos','country'=>'Nigeria','official_url'=>null,'source_url'=>null,'metadata'=>json_encode(['catalogue_source'=>'admin_added']),
   'is_active'=>true,'created_at'=>now(),'updated_at'=>now()
  ]);
  DB::table('education_reference_catalogue')->insert([
   'kind'=>'exam_body','category'=>'national_or_international','name'=>'New Exam Board','catalogue_key'=>hash('sha256','exam_body|national_or_international|New Exam Board'),
   'short_name'=>'NEB','state'=>null,'country'=>'Nigeria','official_url'=>null,'source_url'=>null,'metadata'=>json_encode(['catalogue_source'=>'admin_added']),
   'is_active'=>true,'created_at'=>now(),'updated_at'=>now()
  ]);
  $this->assertDatabaseHas('education_reference_catalogue',['name'=>'Admin Added University','kind'=>'school']);
  $this->assertDatabaseHas('education_reference_catalogue',['name'=>'New Exam Board','kind'=>'exam_body']);
 }
 public function test_admin_csv_import_adds_new_reference_rows_and_skips_duplicates():void {
  $file=UploadedFile::fake()->createWithContent('references.csv',"kind,name,category,short_name,state,country,official_url,source_url\\nschool,CSV University,private_university,CSVU,Lagos,Nigeria,https://csv.example.edu,https://nuc.edu.ng\\nexam_body,CSV Exam Board,national_or_international,CEB,,Nigeria,https://exam.example.org,https://example.org\\n");
  $request=Request::create('/admin/education/references/import-csv','POST',[],[],['file'=>$file]);
  $request->setUserResolver(fn()=>(object)['id'=>1]);
  $response=app(EducationReferenceImportController::class)->importCsv($request);
  $this->assertSame(302,$response->getStatusCode());
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'school','name'=>'CSV University','category'=>'private_university','created_by'=>1]);
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'exam_body','name'=>'CSV Exam Board']);
  $duplicate=UploadedFile::fake()->createWithContent('duplicates.csv',"kind,name,category\\nschool,CSV University,private_university\\n");
  $second=Request::create('/admin/education/references/import-csv','POST',[],[],['file'=>$duplicate]);
  $second->setUserResolver(fn()=>(object)['id'=>1]);
  app(EducationReferenceImportController::class)->importCsv($second);
  $this->assertSame(1,DB::table('education_reference_catalogue')->where('kind','school')->where('name','CSV University')->count());
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
