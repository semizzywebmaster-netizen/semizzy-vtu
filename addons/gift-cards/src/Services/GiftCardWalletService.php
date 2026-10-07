<?php
namespace Semizzy\Addons\GiftCards\Services;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\GiftCards\Models\GiftCardOrder;

class GiftCardWalletService
{
 public function reserve(GiftCardOrder $order): void
 {
  DB::transaction(function() use($order){
   $wallet=$this->wallet($order); $key="giftcard:{$order->id}:reserve";
   if(WalletMovement::where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
   $amount=$this->minor((string)$order->total); $before=(string)$wallet->available_minor; $held=(string)$wallet->held_minor;
   if($this->cmp($before,$amount)<0) throw new RuntimeException('Insufficient wallet balance.');
   $wallet->available_minor=$this->sub($before,$amount); $wallet->held_minor=$this->add($held,$amount); $wallet->save();
   $this->movement($wallet,$order,$key,'reserve',$amount,$before,(string)$wallet->available_minor,$held,(string)$wallet->held_minor);
  });
 }
 public function settle(GiftCardOrder $order): void
 {
  DB::transaction(function() use($order){
   $wallet=$this->wallet($order); $key="giftcard:{$order->id}:settle";
   if(WalletMovement::where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
   $amount=$this->minor((string)$order->total); $before=(string)$wallet->available_minor; $held=(string)$wallet->held_minor;
   if($this->cmp($held,$amount)<0) throw new RuntimeException('Wallet hold is inconsistent.');
   $wallet->held_minor=$this->sub($held,$amount); $wallet->save();
   $this->movement($wallet,$order,$key,'settle',$amount,$before,(string)$wallet->available_minor,$held,(string)$wallet->held_minor);
  });
 }
 public function release(GiftCardOrder $order): void
 {
  DB::transaction(function() use($order){
   $wallet=$this->wallet($order); $key="giftcard:{$order->id}:release";
   if(WalletMovement::where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
   $amount=$this->minor((string)$order->total); $before=(string)$wallet->available_minor; $held=(string)$wallet->held_minor;
   if($this->cmp($held,$amount)<0) throw new RuntimeException('Wallet hold is inconsistent.');
   $wallet->held_minor=$this->sub($held,$amount); $wallet->available_minor=$this->add($before,$amount); $wallet->save();
   $this->movement($wallet,$order,$key,'release',$amount,$before,(string)$wallet->available_minor,$held,(string)$wallet->held_minor);
  });
 }
 private function wallet(GiftCardOrder $order): WalletAccount
 {
  $wallet=WalletAccount::where('user_id',$order->user_id)->where('currency',$order->currency)->lockForUpdate()->first();
  if(!$wallet || $wallet->status!=='active') throw new RuntimeException('User wallet is not available.');
  return $wallet;
 }
 private function movement(WalletAccount $wallet,GiftCardOrder $order,string $key,string $type,string $amount,string $ab,string $aa,string $hb,string $ha): void
 {
  WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>$key,'reference'=>$order->order_reference,'type'=>$type,'amount_minor'=>$amount,'currency'=>$wallet->currency,'available_before_minor'=>$ab,'available_after_minor'=>$aa,'held_before_minor'=>$hb,'held_after_minor'=>$ha,'metadata'=>['gift_card_order_id'=>$order->id]]);
 }
 private function minor(string $amount): string
 {
  if(!preg_match('/^\d+(?:\.\d{1,2})?$/',$amount)) throw new RuntimeException('Invalid monetary amount.');
  [$whole,$frac]=array_pad(explode('.',$amount,2),2,''); return ltrim($whole.$this->pad($frac),'0')?:'0';
 }
 private function pad(string $v): string { return str_pad($v,2,'0'); }
 private function add(string $a,string $b): string { if(function_exists('bcadd')) return bcadd($a,$b,0); return (string)((int)$a+(int)$b); }
 private function sub(string $a,string $b): string { if(function_exists('bcsub')) return bcsub($a,$b,0); return (string)((int)$a-(int)$b); }
 private function cmp(string $a,string $b): int { $a=ltrim($a,'0')?:'0';$b=ltrim($b,'0')?:'0';return strlen($a)<=>strlen($b) ?: strcmp($a,$b); }
}