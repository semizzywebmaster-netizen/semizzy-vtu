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

public function test_duplicate_provider_sms_is_idempotent_within_same_order(): void {
 $u=User::factory()->create();
 $o=SocialServiceOrder::create(['reference'=>'SOC-SMS-IDEMP','user_id'=>$u->id,'order_type'=>'number','status'=>'fulfilled','amount'=>'100','currency'=>'NGN']);
 $s=app(SocialServicesService::class);
 $a=$s->ingestSms($o,'Code 123456','Service','provider-msg-1');
 $b=$s->ingestSms($o,'Code 123456','Service','provider-msg-1');
 $this->assertSame($a->id,$b->id);
 $this->assertSame(1,\Semizzy\Addons\Social\Models\SocialNumberSms::where('order_id',$o->id)->count());

}

public function test_provider_sms_identifier_cannot_cross_orders(): void {
 $a=User::factory()->create(); $b=User::factory()->create();
 $first=SocialServiceOrder::create(['reference'=>'SOC-SMS-A','user_id'=>$a->id,'order_type'=>'number','status'=>'fulfilled','amount'=>'100','currency'=>'NGN']);
 $second=SocialServiceOrder::create(['reference'=>'SOC-SMS-B','user_id'=>$b->id,'order_type'=>'number','status'=>'fulfilled','amount'=>'100','currency'=>'NGN']);
 $s=app(SocialServicesService::class);
 $s->ingestSms($first,'Code 111111','Service','shared-provider-id');
 $this->expectException(\\RuntimeException::class);
 $s->ingestSms($second,'Code 222222','Service','shared-provider-id');
}

public function test_expired_number_cannot_receive_sms(): void {
 $u=User::factory()->create();
 $o=SocialServiceOrder::create(['reference'=>'SOC-SMS-EXP','user_id'=>$u->id,'order_type'=>'number','status'=>'fulfilled','amount'=>'100','currency'=>'NGN','expires_at'=>now()->subMinute()]);
 $this->expectException(\\RuntimeException::class);
 app(SocialServicesService::class)->ingestSms($o,'Expired code','Service','expired-msg');
}


public function test_expired_number_cannot_be_purchased(): void {
 $u=User::factory()->create();
 $n=SocialNumberInventory::create(['country_code'=>'+1','country_name'=>'United States','service_key'=>'test','phone_number'=>'+15550000001','phone_hash'=>hash('sha256','15550000001'),'fulfillment_mode'=>'manual','price'=>'10.00','currency'=>'NGN','status'=>'available','expires_at'=>now()->subMinute()]);
 $this->expectException(\\RuntimeException::class);
 app(SocialServicesService::class)->createNumberOrder($u->id,$n->id);
 $this->assertDatabaseHas('social_number_inventory',['id'=>$n->id,'status'=>'disabled']);
}

}