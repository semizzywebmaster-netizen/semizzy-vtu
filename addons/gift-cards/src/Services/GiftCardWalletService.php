<?php
namespace Semizzy\Addons\GiftCards\Services;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\GiftCards\Models\GiftCardOrder;
class GiftCardWalletService {
 public function reserve(GiftCardOrder $order): void { $this->move($order,'reserve',false); }
 public function settle(GiftCardOrder $order): void { $this->move($order,'settle',false); }
 public function release(GiftCardOrder $order): void { $this->move($order,'release',true); }
 public function refund(GiftCardOrder $order): void { $this->move($order,'refund',true); }
 private function move(GiftCardOrder $order,string $type,bool $returnHeld): void {
  DB::transaction(function() use($order,$type,$returnHeld){
   $wallet=$this->wallet($order); $key="giftcard:{$order->id}:{$type}";
   if(WalletMovement::where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
   $amount=$this->minor((string)$order->total); $ab=(string)$wallet->available_minor; $hb=(string)$wallet->held_minor;
   if($type==='reserve'){if($this->cmp($ab,$amount)<0) throw new RuntimeException('Insufficient wallet balance.');$aa=$this->sub($ab,$amount);$ha=$this->add($hb,$amount);}
   elseif($type!=='refund' && $this->cmp($hb,$amount)<0) throw new RuntimeException('Wallet hold is inconsistent.');
   elseif($returnHeld && $type==='release'){$aa=$this->add($ab,$amount);$ha=$this->sub($hb,$amount);}
   elseif($type==='refund'){$aa=$this->add($ab,$amount);$ha=$hb;}
   else {$aa=$ab;$ha=$this->sub($hb,$amount);}
   $wallet->available_minor=$aa;$wallet->held_minor=$ha;$wallet->save();
   WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>$key,'reference'=>$order->order_reference,'type'=>$type,'amount_minor'=>$amount,'currency'=>$wallet->currency,'available_before_minor'=>$ab,'available_after_minor'=>$aa,'held_before_minor'=>$hb,'held_after_minor'=>$ha,'metadata'=>['gift_card_order_id'=>$order->id]]);
  });
 }
 private function wallet(GiftCardOrder $order): WalletAccount {$wallet=WalletAccount::where('user_id',$order->user_id)->where('currency',$order->wallet_currency??$order->currency)->lockForUpdate()->first();if(!$wallet||$wallet->status!=='active')throw new RuntimeException('User wallet is not available.');return $wallet;}
 private function minor(string $amount): string {if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$amount))throw new RuntimeException('Invalid monetary amount.');[$w,$f]=array_pad(explode('.',$amount,2),2,'');return ltrim($w.str_pad($f,2,'0'),'0')?:'0';}
 private function add(string $a,string $b): string {if(function_exists('bcadd'))return bcadd($a,$b,0);return $this->intAdd($a,$b);}
 private function sub(string $a,string $b): string {if(function_exists('bcsub'))return bcsub($a,$b,0);if($this->cmp($a,$b)<0)throw new RuntimeException('Wallet arithmetic underflow.');return $this->intSub($a,$b);}
 private function intAdd(string $a,string $b): string {$out='';$carry=0;$i=strlen($a)-1;$j=strlen($b)-1;while($i>=0||$j>=0||$carry){$n=($i>=0?(ord($a[$i--])-48):0)+($j>=0?(ord($b[$j--])-48):0)+$carry;$out=($n%10).$out;$carry=intdiv($n,10);}return ltrim($out,'0')?:'0';}
 private function intSub(string $a,string $b): string {$out='';$borrow=0;$i=strlen($a)-1;$j=strlen($b)-1;while($i>=0){$n=ord($a[$i--])-48-$borrow-($j>=0?ord($b[$j--])-48:0);if($n<0){$n+=10;$borrow=1;}else$borrow=0;$out=$n.$out;}return ltrim($out,'0')?:'0';}
 private function cmp(string $a,string $b): int {$a=ltrim($a,'0')?:'0';$b=ltrim($b,'0')?:'0';return strlen($a)<=>strlen($b)?:strcmp($a,$b);}
}