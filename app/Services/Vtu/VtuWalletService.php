<?php
namespace App\Services\Vtu;
use App\Models\WalletAccount;
use App\Models\VtuTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class VtuWalletService{
 public function reserve(VtuTransaction $tx):void{DB::transaction(function()use($tx){$w=WalletAccount::query()->where('user_id',$tx->user_id)->where('currency',$tx->currency)->lockForUpdate()->first();if(!$w)throw new RuntimeException('User wallet is not available.');$a=(string)$w->available_minor;$t=(string)$tx->total_minor;if(function_exists('bccomp')){if(bccomp($a,$t,0)<0)throw new RuntimeException('Insufficient wallet balance.');$w->available_minor=bcsub($a,$t,0);$w->held_minor=bcadd((string)$w->held_minor,$t,0);}else{if((int)$a<(int)$t)throw new RuntimeException('Insufficient wallet balance.');$w->available_minor=(string)((int)$a-(int)$t);$w->held_minor=(string)((int)$w->held_minor+(int)$t);}$w->save();});}
 public function settle(VtuTransaction $tx,bool $success):void{DB::transaction(function()use($tx,$success){$w=WalletAccount::query()->where('user_id',$tx->user_id)->where('currency',$tx->currency)->lockForUpdate()->firstOrFail();$t=(string)$tx->total_minor;$h=(string)$w->held_minor;if(function_exists('bccomp')){if(bccomp($h,$t,0)<0)throw new RuntimeException('Wallet hold is inconsistent.');$w->held_minor=bcsub($h,$t,0);if(!$success)$w->available_minor=bcadd((string)$w->available_minor,$t,0);}else{if((int)$h<(int)$t)throw new RuntimeException('Wallet hold is inconsistent.');$w->held_minor=(string)((int)$h-(int)$t);if(!$success)$w->available_minor=(string)((int)$w->available_minor+(int)$t);}$w->save();});}
}