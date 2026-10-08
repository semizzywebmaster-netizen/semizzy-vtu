<?php
namespace Semizzy\Addons\Smm\Services;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Semizzy\Addons\Smm\Models\SmmOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SmmWalletService
{
    public function walletForOrderUser(int $userId,string $currency): WalletAccount
    {
        $wallet=WalletAccount::where('user_id',$userId)->where('currency',$currency)->lockForUpdate()->first();
        if(!$wallet||$wallet->status!=='active') throw new RuntimeException('User wallet is not available.');
        return $wallet;
    }

    public function reserve(SmmOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $wallet=$this->wallet($order); if($order->wallet_account_id!==null && (int)$order->wallet_account_id!==(int)$wallet->id) throw new RuntimeException('SMM order wallet binding is invalid.'); $key="smm:{$order->id}:reserve";
            if(WalletMovement::where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
            $beforeA=(string)$wallet->available_minor; $beforeH=(string)$wallet->held_minor; $amount=(string)$order->amount_minor;
            if($this->cmp($beforeA,$amount)<0) throw new RuntimeException('Insufficient wallet balance.');
            $wallet->available_minor=$this->sub($beforeA,$amount); $wallet->held_minor=$this->add($beforeH,$amount); $wallet->save();
            $this->movement($wallet,$key,$order,'reserve',$amount,$beforeA,(string)$wallet->available_minor,$beforeH,(string)$wallet->held_minor);
        });
    }

    public function settle(SmmOrder $order,bool $success): void
    {
        DB::transaction(function () use ($order,$success): void {
            ".($success?'success':'failure');
            if(WalletMovement::where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
            $opposite="smm:{$order->id}:settle:".($success?'failure':'success');
            if(WalletMovement::where('wallet_account_id',$wallet->id)->where('operation_key',$opposite)->exists()) throw new RuntimeException('Wallet settlement already finalized with the opposite outcome.');
            $beforeA=(string)$wallet->available_minor; $beforeH=(string)$wallet->held_minor; $amount=(string)$order->amount_minor;
            if($this->cmp($beforeH,$amount)<0) throw new RuntimeException('Wallet hold is inconsistent.');
            $wallet->held_minor=$this->sub($beforeH,$amount);
            if(!$success) $wallet->available_minor=$this->add($beforeA,$amount);
            $wallet->save();
            $this->movement($wallet,$key,$order,$success?'settle_success':'settle_failure',$amount,$beforeA,(string)$wallet->available_minor,$beforeH,(string)$wallet->held_minor);
        });
    }

    private function wallet(SmmOrder $order): WalletAccount
    {
        $wallet=WalletAccount::where('user_id',$order->user_id)->where('currency',$order->currency)->lockForUpdate()->first();
        if(!$wallet || $wallet->status!=='active') throw new RuntimeException('User wallet is not available.');
        return $wallet;
    }
    private function movement(WalletAccount $wallet,string $key,SmmOrder $order,string $type,string $amount,string $a0,string $a1,string $h0,string $h1):void
    { WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>$key,'reference'=>$order->reference,'type'=>$type,'amount_minor'=>$amount,'currency'=>$wallet->currency,'available_before_minor'=>$a0,'available_after_minor'=>$a1,'held_before_minor'=>$h0,'held_after_minor'=>$h1,'metadata'=>['smm_order_id'=>$order->id]]); }
    private function add(string $a,string $b):string { if(function_exists('bcadd')) return bcadd($a,$b,0); if(!$this->fits($a)||!$this->fits($b)) throw new RuntimeException('Large wallet amounts require BCMath.'); return (string)((int)$a+(int)$b); }
    private function sub(string $a,string $b):string { if(function_exists('bcsub')) return bcsub($a,$b,0); if(!$this->fits($a)||!$this->fits($b)) throw new RuntimeException('Large wallet amounts require BCMath.'); return (string)((int)$a-(int)$b); }
    private function fits(string $v):bool { $v=ltrim($v,'0'); return ctype_digit($v===''?'0':$v)&&PHP_INT_SIZE>=8&&strlen($v)<=17; }
    private function cmp(string $a,string $b):int { $a=ltrim($a,'0')?:'0'; $b=ltrim($b,'0')?:'0'; return strlen($a)<=>strlen($b) ?: strcmp($a,$b); }
}