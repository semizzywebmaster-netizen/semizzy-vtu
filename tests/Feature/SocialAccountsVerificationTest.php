<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Semizzy\Addons\Social\Models\SocialAccount;
use Semizzy\Addons\Social\Models\SocialVerificationRequest;
use Semizzy\Addons\Social\Services\SocialVerificationService;

class SocialAccountsVerificationTest extends TestCase
{
 use RefreshDatabase;

 public function test_user_can_link_social_account(): void
 {
  $user=User::factory()->create();
  $this->actingAs($user)->post('/social/accounts',['platform'=>'instagram','username'=>'demo_user'])->assertStatus(201);
  $this->assertDatabaseHas('social_accounts',['user_id'=>$user->id,'platform'=>'instagram','username'=>'demo_user']);
 }

 public function test_duplicate_account_is_rejected_by_database_constraint(): void
 {
  $user=User::factory()->create();
  SocialAccount::create(['user_id'=>$user->id,'platform'=>'x','username'=>'demo']);
  $this->expectException(\Throwable::class);
  SocialAccount::create(['user_id'=>$user->id,'platform'=>'x','username'=>'demo']);
 }

 public function test_verification_request_is_pending_and_expires(): void
 {
  $user=User::factory()->create();
  $account=SocialAccount::create(['user_id'=>$user->id,'platform'=>'x','username'=>'demo']);
  $request=app(SocialVerificationService::class)->request($account,$user->id);
  $this->assertSame('pending',$request->status);
  $request->update(['expires_at'=>now()->subMinute()]);
  $fresh=app(SocialVerificationService::class)->requery($request);
  $this->assertSame('expired',$fresh->status);
  $this->assertDatabaseHas('social_accounts',['id'=>$account->id,'verification_status'=>'expired']);
 }

 public function test_manual_approval_only_changes_pending_request(): void
 {
  $user=User::factory()->create();
  $account=SocialAccount::create(['user_id'=>$user->id,'platform'=>'x','username'=>'demo']);
  $request=app(SocialVerificationService::class)->request($account,$user->id);
  $approved=app(SocialVerificationService::class)->approveManual($request);
  $this->assertSame('verified',$approved->status);
  $this->assertDatabaseHas('social_accounts',['id'=>$account->id,'verification_status'=>'verified']);
 }

 public function test_service_rejects_wrong_owner(): void
 {
  $owner=User::factory()->create(); $other=User::factory()->create();
  $account=SocialAccount::create(['user_id'=>$owner->id,'platform'=>'x','username'=>'demo']);
  $this->expectException(\RuntimeException::class);
  app(SocialVerificationService::class)->request($account,$other->id);
 }
}