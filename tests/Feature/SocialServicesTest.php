<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Semizzy\Addons\Social\Models\SocialNumberInventory;
use Semizzy\Addons\Social\Models\SocialServiceOrder;
use Semizzy\Addons\Social\Services\SocialServicesService;
use Tests\TestCase;
class SocialServicesTest extends TestCase {
 use RefreshDatabase;
 public function test_user_can_create_number_order_from_admin_inventory():void{$u=User::factory()->create();$n=SocialNumberInventory::create(['country_code'=>'GB','country_name'=>'United Kingdom','phone_number'=>'+441234567890','phone_hash'=>hash('sha256','441234567890'),'service_key'=>'instagram','fulfillment_mode'=>'manual','price'=>'1000.00','currency'=>'NGN','status'=>'available']);$o=app(SocialServicesService::class)->createNumberOrder($u->id,$n->id);$this->assertSame('pending_payment',$o->status);$this->assertDatabaseHas('social_number_inventory',['id'=>$n->id,'status'=>'reserved']);}
 public function test_sms_is_scoped_to_number_order():void{$u=User::factory()->create();$o=SocialServiceOrder::create(['reference'=>'SOC-N-TEST','user_id'=>$u->id,'order_type'=>'number','status'=>'fulfilled','amount'=>'100','currency'=>'NGN']);$sms=app(SocialServicesService::class)->ingestSms($o,'Your code is 123456','Service','msg-1');$this->assertSame($o->id,$sms->order_id);}
 public function test_wallet_payment_is_idempotent():void{$u=User::factory()->create();\App\Models\WalletAccount::create(['user_id'=>$u->id,'currency'=>'NGN','available_minor'=>'50000','held_minor'=>'0','status'=>'active']);$o=SocialServiceOrder::create(['reference'=>'SOC-PAY-TEST','user_id'=>$u->id,'order_type'=>'number','status'=>'pending_payment','amount'=>'100.00','currency'=>'NGN']);$s=app(SocialServicesService::class);$first=$s->payFromWallet($u,$o);$second=$s->payFromWallet($u,$o);$this->assertSame($first->id,$second->id);$this->assertSame('paid',$second->payment_status);$this->assertSame('40000',(string)\App\Models\WalletAccount::where('user_id',$u->id)->value('available_minor'));$this->assertSame(1,\App\Models\WalletMovement::where('operation_key','social:payment:'.$o->reference)->count());}
 public function test_user_cannot_read_another_users_sms():void{$a=User::factory()->create();$b=User::factory()->create();$o=SocialServiceOrder::create(['reference'=>'SOC-N-TEST2','user_id'=>$a->id,'order_type'=>'number','status'=>'fulfilled','amount'=>'100','currency'=>'NGN']);$this->actingAs($b)->get('/social-services/numbers/'.$o->id.'/sms')->assertNotFound();}
}