<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
class SecurityHardeningTest extends TestCase {
 use RefreshDatabase;
 public function test_api_token_routes_are_rate_limited_and_log_throttling():void{
  $u=User::create(['name'=>'Throttle Test','email'=>'throttle@example.test','password'=>'Strong-Test-Password-123!','role'=>'USER','status'=>'active']);
  $this->actingAs($u);
  for($i=0;$i<10;$i++) $this->getJson('/api/v1/tokens')->assertStatus(200);
  $this->getJson('/api/v1/tokens')->assertStatus(429);
  $this->assertDatabaseHas('security_events',['event'=>'security.rate_limited','severity'=>'warning']);
  RateLimiter::clear('security:api.tokens.index:'.sha1((string)$u->id));
 }
 public function test_security_events_page_requires_admin_area_role():void{
  $u=User::create(['name'=>'Normal User','email'=>'normal@example.test','password'=>'Strong-Test-Password-123!','role'=>'USER','status'=>'active']);
  $this->actingAs($u)->get('/admin/security-events')->assertStatus(403);
 }
}