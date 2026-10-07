<?php
namespace Addons\InsuranceProtection\Services;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Addons\InsuranceProtection\Models\InsurancePolicy;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class InsuranceWalletService {
 public function reserve(InsurancePolicy $p): void {$this->move($p,'reserve');}
 public function settle(InsurancePolicy $p): void {$this->move($p,'settle');}
 public function release(InsurancePolicy $p): void {$this->move($p,'release');}
 public function reserveRenewal(InsurancePolicy $p,string $key): void {$this->move($p,'renewal_reserve',$key);}
 public function settleRenewal(InsurancePolicy $p,string $key): void {$this->move($p,'renewal_settle',$key);}
 public function releaseRenewal(InsurancePolicy $p,string $key): void {$this->move($p,'renewal_release',$key);}
 private function move(InsurancePolicy $p,string $type,?string $suffix=null): void {DB::transaction(function()use($p,$type,$suffix){$wallet=WalletAccount::where('user_id',$p->user_id)->where('currency',$p->currency)->lockForUpdate()->first();if(!$wallet||$wallet->status!=='active')throw new RuntimeException('User wallet is not available.');$key='insurance:'.$p->id.':'.$type.':'.($suffix?:'default');if(WalletMovement::where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists())return;$amt=(int)$p->premium_minor;$available=(int)$wallet->available_minor;$held=(int)$wallet->held_minor;if(in_array($type,['reserve','renewal_reserve'],true)){if($available<$amt)throw new RuntimeException('Insufficient wallet balance.');$na=$available-$amt;$nh=$held+$amt;}elseif($held<$amt)throw new RuntimeException('Wallet hold is inconsistent.');elseif($type==='release'){$na=$available+$amt;$nh=$held-$amt;}else{$na=$available;$nh=$held-$amt;}$wallet->available_minor=$na;$wallet->held_minor=$nh;$wallet->save();WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>$key,'reference'=>$p->reference,'type'=>$type,'amount_minor'=>$amt,'currency'=>$p->currency,'available_before_minor'=>$available,'available_after_minor'=>$na,'held_before_minor'=>$held,'held_after_minor'=>$nh,'metadata'=>['insurance_policy_id'=>$p->id]]);});}
}