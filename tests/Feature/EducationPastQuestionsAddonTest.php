<?php
namespace Tests\Feature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Semizzy\Addons\Education\Http\Controllers\EducationReferenceImportController;
use Semizzy\Addons\Education\Models\EducationLibraryItem;
use Semizzy\Addons\Education\Services\EducationInstitutionImportService;
use Semizzy\Addons\Education\Services\EducationInstitutionSyncRunService;
use Semizzy\Addons\Education\Services\NcceAccreditedCollegesAdapter;
use Semizzy\Addons\Education\Services\NucUniversityDirectoryAdapter;
use Tests\TestCase;
class EducationPastQuestionsAddonTest extends TestCase {
 private bool $createdUsersTable=false;
 protected function setUp():void {
  parent::setUp();
  if(!Schema::hasTable('users')) {
   Schema::create('users',function(Blueprint $t){$t->id();$t->string('name')->nullable();$t->string('email')->nullable();$t->string('password')->nullable();$t->timestamps();});
   $this->createdUsersTable=true;
  }
  Schema::dropIfExists('education_institution_sync_runs');
  Schema::dropIfExists('education_institutions');
  Schema::dropIfExists('education_library_purchases');
  Schema::dropIfExists('education_library_items');
  Schema::dropIfExists('education_reference_categories');
  Schema::dropIfExists('education_reference_catalogue');
  $referenceMigration=require base_path('addons/education/database/migrations/2026_10_09_110100_create_and_import_education_reference_catalogue.php');
  $referenceMigration->up();
  $adminReferenceMigration=require base_path('addons/education/database/migrations/2026_10_09_110200_add_admin_managed_education_reference_fields.php');
  $adminReferenceMigration->up();
  $directoryExpansionMigration=require base_path('addons/education/database/migrations/2026_10_09_110300_expand_official_school_reference_catalogue.php');
  $directoryExpansionMigration->up();
  $institutionMigration=require base_path('addons/education/database/migrations/2026_10_10_130000_create_canonical_education_institutions.php');
  $institutionMigration->up();
  $syncRunMigration=require base_path('addons/education/database/migrations/2026_10_10_140000_create_education_institution_sync_runs.php');
  $syncRunMigration->up();
  $migration=require base_path('addons/education/database/migrations/2026_10_09_110000_create_education_past_question_library.php');
  $migration->up();
 }
 protected function tearDown():void {
  Schema::dropIfExists('education_institution_sync_runs');
  Schema::dropIfExists('education_institutions');
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
  $this->assertContains('2026_10_09_110300_expand_official_school_reference_catalogue.php',$manifest['migrations']);
  $this->assertContains('2026_10_10_130000_create_canonical_education_institutions.php',$manifest['migrations']);
  $this->assertContains('2026_10_10_140000_create_education_institution_sync_runs.php',$manifest['migrations']);
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
  $this->assertGreaterThanOrEqual(100,DB::table('education_reference_catalogue')->where('kind','school')->count());
  $this->assertGreaterThanOrEqual(3,DB::table('education_reference_catalogue')->where('kind','school')->distinct()->count('category'));
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'exam_body','short_name'=>'WAEC']);
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'school','name'=>'University of Lagos','category'=>'federal_university','source_url'=>'https://enuc.nuc.edu.ng/nus']);
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'school','name'=>'A.D. Rufa’i College of Education, Legal and General Studies','source_url'=>'https://ncce.gov.ng/AccreditedColleges']);
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'school','name'=>'Miva Open University','category'=>'private_university']);
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'school','name'=>'National University of Science and Technology, Abuja','category'=>'federal_university']);
 }
 public function test_official_directory_expansion_is_safe_to_rerun_without_duplicates():void {
  $before=DB::table('education_reference_catalogue')->where('kind','school')->count();
  $migration=require base_path('addons/education/database/migrations/2026_10_09_110300_expand_official_school_reference_catalogue.php');
  $migration->up();
  $after=DB::table('education_reference_catalogue')->where('kind','school')->count();
  $this->assertSame($before,$after);
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
  $file=UploadedFile::fake()->createWithContent('references.csv',"kind,name,category,short_name,state,country,official_url,source_url\nschool,CSV University,private_university,CSVU,Lagos,Nigeria,https://csv.example.edu,https://nuc.edu.ng\nexam_body,CSV Exam Board,national_or_international,CEB,,Nigeria,https://exam.example.org,https://example.org\n");
  $request=Request::create('/admin/education/references/import-csv','POST',[],[],['file'=>$file]);
  $request->setUserResolver(fn()=>(object)['id'=>1]);
  $response=app(EducationReferenceImportController::class)->importCsv($request);
  $this->assertSame(302,$response->getStatusCode());
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'school','name'=>'CSV University','category'=>'private_university','created_by'=>1]);
  $this->assertDatabaseHas('education_reference_catalogue',['kind'=>'exam_body','name'=>'CSV Exam Board']);
  $duplicate=UploadedFile::fake()->createWithContent('duplicates.csv',"kind,name,category\nschool,CSV University,private_university\n");
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
 public function test_canonical_institution_migration_backfills_legacy_school_catalogue_without_deleting_it():void {
  $this->assertTrue(Schema::hasTable('education_institutions'));
  $legacy=DB::table('education_reference_catalogue')->where('kind','school')->where('name','University of Lagos')->first();
  $this->assertNotNull($legacy);
  $this->assertDatabaseHas('education_institutions',['source_key'=>'catalogue:'.$legacy->catalogue_key,'name'=>'University of Lagos','review_status'=>'approved','active'=>true]);
  $this->assertDatabaseHas('education_reference_catalogue',['id'=>$legacy->id,'name'=>'University of Lagos']);
  $this->assertSame(DB::table('education_reference_catalogue')->where('kind','school')->count(),DB::table('education_institutions')->where('import_source','education_reference_catalogue')->count());
 }
 public function test_institution_sync_is_idempotent_and_new_records_require_review():void {
  $service=app(EducationInstitutionImportService::class);
  $record=['name'=>'Example State University','category'=>'state_university','state'=>'Lagos','ownership'=>'state','external_id'=>'test:example-state-university','source_url'=>'https://source.example.test/list'];
  $first=$service->import([$record],'test-feed');
  $this->assertSame(1,$first['created']);
  $this->assertSame(1,$first['review_required']);
  $this->assertDatabaseHas('education_institutions',['external_id'=>'test:example-state-university','name'=>'Example State University','review_status'=>'pending','active'=>false]);
  $second=$service->import([$record],'test-feed');
  $this->assertSame(0,$second['created']);
  $this->assertSame(1,$second['updated']);
  $this->assertSame(1,DB::table('education_institutions')->where('external_id','test:example-state-university')->count());
 }
 public function test_sync_does_not_overwrite_an_approved_institution_or_reactivate_a_rejected_one():void {
  $service=app(EducationInstitutionImportService::class);
  $record=['name'=>'Manually Verified University','category'=>'university','state'=>'Oyo','external_id'=>'test:verified-university'];
  $service->import([$record],'test-feed');
  DB::table('education_institutions')->where('external_id','test:verified-university')->update(['name'=>'Admin Corrected University','review_status'=>'approved','reviewed_by'=>1,'reviewed_at'=>now(),'active'=>true]);
  $result=$service->import([array_merge($record,['name'=>'Feed Changed University'])],'test-feed');
  $this->assertSame(1,$result['skipped']);
  $this->assertDatabaseHas('education_institutions',['external_id'=>'test:verified-university','name'=>'Admin Corrected University','review_status'=>'approved','active'=>true]);
  DB::table('education_institutions')->where('external_id','test:verified-university')->update(['review_status'=>'rejected','active'=>false]);
  $rejected=$service->import([$record],'test-feed');
  $this->assertSame(1,$rejected['rejected']);
  $this->assertDatabaseHas('education_institutions',['external_id'=>'test:verified-university','review_status'=>'rejected','active'=>false]);
 }

 public function test_external_ids_are_namespaced_by_source_and_do_not_merge_unrelated_records():void {
  $service=app(EducationInstitutionImportService::class);
  $service->import([['name'=>'North University','category'=>'university','state'=>'Lagos','external_id'=>'42']],'feed-a');
  $service->import([['name'=>'South College','category'=>'college','state'=>'Oyo','external_id'=>'42']],'feed-b');
  $this->assertSame(2,DB::table('education_institutions')->where('external_id','42')->count());
  $this->assertDatabaseHas('education_institutions',['external_id'=>'42','import_source'=>'feed-a','name'=>'North University']);
  $this->assertDatabaseHas('education_institutions',['external_id'=>'42','import_source'=>'feed-b','name'=>'South College']);
 }
 public function test_migration_rollback_refuses_to_drop_non_legacy_registry_records():void {
  app(EducationInstitutionImportService::class)->import([['name'=>'Imported University','category'=>'university','external_id'=>'rollback-guard-test']],'rollback-test-feed');
  $migration=require base_path('addons/education/database/migrations/2026_10_10_130000_create_canonical_education_institutions.php');
  try {
   $migration->down();
   $this->fail('Rollback should refuse to drop canonical feed records.');
  } catch (\RuntimeException $exception) {
   $this->assertStringContainsString('Cannot roll back the canonical institution registry',$exception->getMessage());
  }
  $this->assertTrue(Schema::hasTable('education_institutions'));
  $this->assertDatabaseHas('education_institutions',['external_id'=>'rollback-guard-test','review_status'=>'pending','active'=>false]);
 }


 public function test_sync_run_requires_all_pages_and_exact_expected_total_before_completion():void {
  $service=app(EducationInstitutionSyncRunService::class);
  $run=$service->start('verified-test-feed',1);
  $run=$service->recordPage($run,['created'=>2,'updated'=>0,'skipped'=>0,'rejected'=>0,'review_required'=>2],'page-2',3);
  try {
   $service->complete($run);
   $this->fail('A run with a next-page cursor must not complete.');
  } catch (\DomainException $exception) {
   $this->assertStringContainsString('next-page cursor',$exception->getMessage());
  }
  $run=$service->recordPage($run,['created'=>0,'updated'=>0,'skipped'=>1,'rejected'=>0],null,3);
  $completed=$service->complete($run);
  $this->assertSame('completed',$completed->status);
  $this->assertSame(2,$completed->pages_processed);
  $this->assertSame(3,$completed->records_seen);
  $this->assertNotNull($completed->finished_at);
 }

 public function test_sync_run_marks_low_result_or_partial_pagination_incomplete():void {
  $service=app(EducationInstitutionSyncRunService::class);
  $lowRun=$service->start('sparse-test-feed',2);
  $lowRun=$service->recordPage($lowRun,['created'=>1,'updated'=>0,'skipped'=>0,'rejected'=>0],null,1);
  $lowResult=$service->complete($lowRun);
  $this->assertSame('incomplete',$lowResult->status);
  $this->assertStringContainsString('below the configured minimum',$lowResult->error_summary);
  $this->assertNotNull($lowResult->finished_at);

  $partialRun=$service->start('partial-test-feed',1);
  $partialRun=$service->recordPage($partialRun,['created'=>1,'updated'=>0,'skipped'=>0,'rejected'=>0],null,2);
  $partialResult=$service->complete($partialRun);
  $this->assertSame('incomplete',$partialResult->status);
  $this->assertStringContainsString('did not match the source-reported total',$partialResult->error_summary);
 }

 public function test_failed_sync_run_redacts_credentials_and_cannot_be_reopened():void {
  $service=app(EducationInstitutionSyncRunService::class);
  $run=$service->start('error-test-feed');
  $failed=$service->fail($run,'API request failed at https://private.example.test/path password=supersecret');
  $this->assertSame('failed',$failed->status);
  $this->assertStringNotContainsString('private.example.test',$failed->error_summary);
  $this->assertStringNotContainsString('supersecret',$failed->error_summary);
  $this->assertStringContainsString('[redacted-url]',$failed->error_summary);
  $this->assertStringContainsString('[redacted-credential]',$failed->error_summary);
  $this->expectException(\DomainException::class);
  $service->complete($failed);
 }

 public function test_sync_run_history_migration_refuses_to_drop_recorded_history():void {
  $service=app(EducationInstitutionSyncRunService::class);
  $service->start('history-guard-feed');
  $migration=require base_path('addons/education/database/migrations/2026_10_10_140000_create_education_institution_sync_runs.php');
  $this->expectException(\RuntimeException::class);
  $migration->down();
 }


 public function test_ncce_official_directory_adapter_validates_complete_feed_and_imports_pending_records():void {
  $rows='';
  for($i=1;$i<=264;$i++) {
   $rows.='<tr><td>'.$i.'</td><td>Test College '.$i.' <a href="/details/'.$i.'">OPEN</a></td><td>Provost</td><td>Private College of Education</td><td>Lagos</td><td><a href="https://college'.$i.'.example.edu">Website</a></td></tr>';
  }
  $html='<html><body><table><thead><tr><th>S/N</th><th>Name</th><th>Provost</th><th>College Ownership</th><th>State</th><th>Website</th></tr></thead><tbody>'.$rows.'</tbody></table></body></html>';
  Http::fake([NcceAccreditedCollegesAdapter::URL=>Http::response($html,200,['Content-Type'=>'text/html; charset=UTF-8'])]);

  $run=app(NcceAccreditedCollegesAdapter::class)->sync();

  $this->assertSame('completed',$run->status);
  $this->assertSame(264,$run->expected_total);
  $this->assertSame(264,$run->records_seen);
  $this->assertSame(264,DB::table('education_institutions')->where('import_source',NcceAccreditedCollegesAdapter::SOURCE)->count());
  $this->assertSame(264,DB::table('education_institutions')->where('import_source',NcceAccreditedCollegesAdapter::SOURCE)->where('review_status','pending')->where('active',false)->count());
  $this->assertDatabaseHas('education_institutions',['name'=>'Test College 1','category'=>'college_of_education','ownership'=>'private','state'=>'Lagos','review_status'=>'pending','active'=>false]);
  Http::assertSent(fn($request)=>$request->url()===NcceAccreditedCollegesAdapter::URL);
 }

 public function test_ncce_official_directory_adapter_rejects_incomplete_or_changed_source_layout():void {
  $adapter=app(NcceAccreditedCollegesAdapter::class);
  $this->expectException(\DomainException::class);
  $adapter->parse('<html><body><table><tr><th>Name</th><th>Data</th></tr><tr><td>Only a partial table</td><td>missing contract</td></tr></table></body></html>');
 }


 public function test_nuc_official_university_directory_imports_complete_html_feed_and_records_sync_counts():void {
  $rows='';
  for($i=1;$i<=328;$i++) {
   $ownership=$i<=77?'Federal':($i<=146?'State':'Private');
   $rows.='<tr><td>'.$i.'</td><td>NUC Test University '.$i.'</td><td>'.(1980+($i%45)).'</td><td>'.$ownership.'</td><td>Lagos</td><td><a href="/university/'.$i.'">Explore</a></td></tr>';
  }
  $html='<html><body><section><div>77 Federal Universities</div><div>69 State Universities</div><div>182 Private Universities</div></section><table><thead><tr><th>#</th><th>University Name</th><th>Year Established</th><th>Ownership</th><th>State</th><th>Action</th></tr></thead><tbody>'.$rows.'</tbody></table></body></html>';
  Http::fake([NucUniversityDirectoryAdapter::URL=>Http::response($html,200,['Content-Type'=>'text/html; charset=UTF-8'])]);

  $run=app(NucUniversityDirectoryAdapter::class)->sync();

  $this->assertSame('completed',$run->status);
  $this->assertSame(328,$run->expected_total);
  $this->assertSame(328,$run->records_seen);
  $this->assertSame(328,DB::table('education_institutions')->where('import_source',NucUniversityDirectoryAdapter::SOURCE)->count());
  $this->assertSame(328,DB::table('education_institutions')->where('import_source',NucUniversityDirectoryAdapter::SOURCE)->where('review_status','pending')->where('active',false)->count());
  $this->assertDatabaseHas('education_institutions',['name'=>'NUC Test University 1','category'=>'university','ownership'=>'federal','state'=>'Lagos','review_status'=>'pending','active'=>false]);
  $this->assertDatabaseHas('education_institutions',['name'=>'NUC Test University 78','category'=>'university','ownership'=>'state']);
  $this->assertDatabaseHas('education_institutions',['name'=>'NUC Test University 147','category'=>'university','ownership'=>'private']);
  Http::assertSent(fn($request)=>$request->url()===NucUniversityDirectoryAdapter::URL);
 }

 public function test_nuc_official_university_directory_rejects_changed_layout_or_incomplete_feed():void {
  $adapter=app(NucUniversityDirectoryAdapter::class);
  $this->expectException(\DomainException::class);
  $adapter->parse('<html><body><table><tr><th>University</th><th>Data</th></tr><tr><td>Only a partial table</td><td>missing contract</td></tr></table></body></html>');
 }

 public function test_nuc_official_university_directory_rejects_ownership_summary_mismatch():void {
  $rows='';
  for($i=1;$i<=328;$i++) {
   $ownership=$i<=77?'Federal':($i<=146?'State':'Private');
   $rows.='<tr><td>'.$i.'</td><td>Mismatch Test University '.$i.'</td><td>2000</td><td>'.$ownership.'</td><td>Lagos</td><td>Explore</td></tr>';
  }
  $html='<html><body><div>76 Federal Universities</div><div>69 State Universities</div><div>182 Private Universities</div><table><thead><tr><th>#</th><th>University Name</th><th>Year Established</th><th>Ownership</th><th>State</th><th>Action</th></tr></thead><tbody>'.$rows.'</tbody></table></body></html>';
  $this->expectException(\DomainException::class);
  app(NucUniversityDirectoryAdapter::class)->parse($html);
 }

}
