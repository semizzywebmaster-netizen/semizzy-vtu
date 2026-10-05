<?php

namespace App\Services\Vtu;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Models\VtuTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VtuWalletService
{
    public function reserve(VtuTransaction $tx): void
    {
        DB::transaction(function () use ($tx): void {
            $wallet=$this->wallet($tx); $key="vtu:{$tx->id}:reserve";
            if(WalletMovement::query()->where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
            $beforeAvailable=(string)$wallet->available_minor; $beforeHeld=(string)$wallet->held_minor; $total=(string)$tx->total_minor;
            if($this->compareIntegerStrings($beforeAvailable,$total)<0) throw new RuntimeException('Insufficient wallet balance.');
            $wallet->available_minor=$this->sub($beforeAvailable,$total); $wallet->held_minor=$this->add($beforeHeld,$total); $wallet->save();
            $this->movement($wallet,$key,$tx,'reserve',$total,$beforeAvailable,(string)$wallet->available_minor,$beforeHeld,(string)$wallet->held_minor);
        });
    }

    public function creditRefund(VtuTransaction $tx): void
    {
        DB::transaction(function () use ($tx): void {
            $wallet=$this->wallet($tx); $key="vtu:{$tx->id}:refund";
            if(WalletMovement::query()->where('wallet_account_id',$wallet->id)->where('operation_key',$key)->exists()) return;
            $beforeAvailable=(string)$wallet->available_minor; $beforeHeld=(string)$wallet->held_minor; $amount=(string)$tx->total_minor;
            $wallet->available_minor=$this->add($beforeAvailable,$amount); $wallet->save();
            $this->movement($wallet,$key,$tx,'refund',$amount,$beforeAvailable,(string)$wallet->available_minor,$beforeHeld,(string)$wallet->held_minor);
        });
    }

    public function settle(VtuTransaction $tx, bool $success): void
    {
        DB::transaction(function () use ($tx,$success): void {
            $wallet=$this->wallet($tx); $key="vtu:{$tx->id}:settle:".($success?'success':'failure');
            $op=WalletMovement::query()->where('wallet_account_id',$wallet->id)->where('operation_key',$key)->first();
            if($op) return;

            $oppositeKey="vtu:{$tx->id}:settle:".($success?'failure':'success');
            if(WalletMovement::query()->where('wallet_account_id',$wallet->id)->where('operation_key',$oppositeKey)->exists()) {
                throw new RuntimeException('Wallet settlement already finalized with the opposite outcome.');
            }

            $beforeAvailable=(string)$wallet->available_minor; $beforeHeld=(string)$wallet->held_minor; $total=(string)$tx->total_minor;
            if($this->compareIntegerStrings($beforeHeld,$total)<0) throw new RuntimeException('Wallet hold is inconsistent.');
            $wallet->held_minor=$this->sub($beforeHeld,$total); if(!$success) $wallet->available_minor=$this->add($beforeAvailable,$total); $wallet->save();
            $this->movement($wallet,$key,$tx,$success?'settle_success':'settle_failure',$total,$beforeAvailable,(string)$wallet->available_minor,$beforeHeld,(string)$wallet->held_minor);
        });
    }

    private function wallet(VtuTransaction $tx): WalletAccount
    {
        $wallet=WalletAccount::query()->where('user_id',$tx->user_id)->where('currency',$tx->currency)->lockForUpdate()->first();
        if(!$wallet) throw new RuntimeException('User wallet is not available.'); return $wallet;
    }

    private function movement(WalletAccount $wallet,string $key,VtuTransaction $tx,string $type,string $amount,string $availableBefore,string $availableAfter,string $heldBefore,string $heldAfter): void
    {
        WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>$key,'reference'=>$tx->reference,'type'=>$type,'amount_minor'=>$amount,'currency'=>$wallet->currency,'available_before_minor'=>$availableBefore,'available_after_minor'=>$availableAfter,'held_before_minor'=>$heldBefore,'held_after_minor'=>$heldAfter,'metadata'=>['vtu_transaction_id'=>$tx->id]]);
    }

    private function add(string $a,string $b): string
    {
        if(function_exists('bcadd')) return bcadd($a,$b,0);
        if(!$this->fitsNativeInteger($a)||!$this->fitsNativeInteger($b)) throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        return (string)((int)$a+(int)$b);
    }

    private function sub(string $a,string $b): string
    {
        if(function_exists('bcsub')) return bcsub($a,$b,0);
        if(!$this->fitsNativeInteger($a)||!$this->fitsNativeInteger($b)) throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        return (string)((int)$a-(int)$b);
    }

    private function fitsNativeInteger(string $value): bool { $value=ltrim($value,'0'); return ctype_digit($value===''?'0':$value)&&PHP_INT_SIZE>=8&&strlen($value)<=17; }

    private function compareIntegerStrings(string $left,string $right): int
    {
        $left=ltrim($left,'0')?:'0'; $right=ltrim($right,'0')?:'0'; return strlen($left)<=>strlen($right) ?: strcmp($left,$right);
    }
}
