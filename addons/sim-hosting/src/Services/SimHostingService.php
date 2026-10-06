<?php

namespace Semizzy\Addons\SimHosting\Services;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\SimHosting\Models\SimHostingNumber;
use Semizzy\Addons\SimHosting\Models\SimHostingProduct;
use Semizzy\Addons\SimHosting\Models\SimHostingRental;
use Semizzy\Addons\SimHosting\Models\SimHostingMovement;

class SimHostingService
{
    private function cmp(string $a, string $b): int
    {
        return function_exists('bccomp') ? bccomp($a, $b, 0) : ((int) $a <=> (int) $b);
    }

    private function sub(string $a, string $b): string
    {
        return function_exists('bcsub') ? bcsub($a, $b, 0) : (string) ((int) $a - (int) $b);
    }

    private function add(string $a, string $b): string
    {
        return function_exists('bcadd') ? bcadd($a, $b, 0) : (string) ((int) $a + (int) $b);
    }

    public function rent(int $userId, array $data): SimHostingRental
    {
        return DB::transaction(function () use ($userId, $data) {
            $existing = SimHostingRental::where('user_id',$userId)
                ->where('idempotency_key',$data['idempotency_key'])->first();
            if ($existing) return $existing->load(['product','number']);

            $product = SimHostingProduct::where('key',$data['product_key'])
                ->where('active',true)->lockForUpdate()->firstOrFail();

            $days=(int)$data['rental_days'];
            $max=$product->max_rental_days ?? $days;
            if ($days > $max) throw new RuntimeException('Requested rental period exceeds the product limit.');

            $number=SimHostingNumber::where('sim_hosting_product_id',$product->id)
                ->where('status','available')->orderBy('id')->lockForUpdate()->firstOrFail();

            $wallet=WalletAccount::where('user_id',$userId)->where('currency',$product->currency)
                ->where('status','active')->lockForUpdate()->firstOrFail();

            $amount=(string)$product->rental_price_minor;
            $before=(string)$wallet->available_minor;
            if($this->cmp($before,$amount)<0) throw new RuntimeException('Insufficient wallet balance.');
            $after=$this->sub($before,$amount);
            $op='sim-hosting:rent:'.$data['idempotency_key'];

            $wallet->available_minor=$after; $wallet->saveOrFail();

            WalletMovement::create([
                'wallet_account_id'=>$wallet->id,'operation_key'=>$op,
                'reference'=>'SIM-WAL-'.strtoupper(Str::random(10)),'type'=>'sim_hosting_rental',
                'amount_minor'=>$amount,'currency'=>$wallet->currency,
                'available_before_minor'=>$before,'available_after_minor'=>$after,
                'held_before_minor'=>(string)$wallet->held_minor,'held_after_minor'=>(string)$wallet->held_minor,
                'metadata'=>['product_key'=>$product->key],
            ]);

            $rental=SimHostingRental::create([
                'user_id'=>$userId,'sim_hosting_product_id'=>$product->id,'sim_hosting_number_id'=>$number->id,
                'reference'=>'SIM-'.strtoupper(Str::random(12)),'currency'=>$product->currency,
                'amount_minor'=>$amount,'rental_days'=>$days,'status'=>'active',
                'starts_at'=>now(),'expires_at'=>now()->addDays($days),
                'idempotency_key'=>$data['idempotency_key'],
            ]);
            $number->status='assigned'; $number->saveOrFail();

            SimHostingMovement::create([
                'sim_hosting_rental_id'=>$rental->id,'user_id'=>$userId,
                'operation_key'=>$op,'reference'=>'SIM-MOV-'.strtoupper(Str::random(10)),
                'type'=>'rental','amount_minor'=>$amount,'currency'=>$rental->currency,
                'metadata'=>['number'=>$number->number],
            ]);
            return $rental->load(['product','number']);
        });
    }

    public function renew(SimHostingRental $rental, int $userId, string $idempotencyKey): SimHostingRental
    {
        return DB::transaction(function () use ($rental,$userId,$idempotencyKey) {
            $r=SimHostingRental::whereKey($rental->id)->where('user_id',$userId)->lockForUpdate()->with('product')->firstOrFail();
            if(!in_array($r->status,['active','expired'],true)) throw new RuntimeException('Rental cannot be renewed.');
            $existing=SimHostingMovement::where('operation_key','sim-hosting:renew:'.$idempotencyKey)->first();
            if($existing) return $r->fresh(['product','number']);
            $wallet=WalletAccount::where('user_id',$userId)->where('currency',$r->currency)->where('status','active')->lockForUpdate()->firstOrFail();
            $amount=(string)($r->product->renewal_price_minor ?? $r->product->rental_price_minor);
            $before=(string)$wallet->available_minor;
            if($this->cmp($before,$amount)<0) throw new RuntimeException('Insufficient wallet balance.');
            $after=$this->sub($before,$amount); $op='sim-hosting:renew:'.$idempotencyKey;
            $wallet->available_minor=$after; $wallet->saveOrFail();
            WalletMovement::create(['wallet_account_id'=>$wallet->id,'operation_key'=>$op,'reference'=>'SIM-REN-WAL-'.strtoupper(Str::random(10)),'type'=>'sim_hosting_renewal','amount_minor'=>$amount,'currency'=>$wallet->currency,'available_before_minor'=>$before,'available_after_minor'=>$after,'held_before_minor'=>(string)$wallet->held_minor,'held_after_minor'=>(string)$wallet->held_minor,'metadata'=>['rental_reference'=>$r->reference]]);
            $start=($r->expires_at && $r->expires_at->isFuture()) ? $r->expires_at : now();
            $r->status='active'; $r->starts_at=$r->starts_at ?: now(); $r->expires_at=$start->copy()->addDays($r->rental_days); $r->renewed_at=now(); $r->saveOrFail();
            SimHostingMovement::create(['sim_hosting_rental_id'=>$r->id,'user_id'=>$userId,'operation_key'=>$op,'reference'=>'SIM-REN-MOV-'.strtoupper(Str::random(10)),'type'=>'renewal','amount_minor'=>$amount,'currency'=>$r->currency]);
            $r->number->update(['status'=>'assigned']);
            return $r->fresh(['product','number']);
        });
    }

    public function expire(int $limit=100): int
    {
        $ids=SimHostingRental::where('status','active')->whereNotNull('expires_at')->where('expires_at','<=',now())->orderBy('id')->limit(max(1,min($limit,500)))->pluck('id');
        $count=0;
        foreach($ids as $id) DB::transaction(function()use($id,&$count){
            $r=SimHostingRental::whereKey($id)->lockForUpdate()->with('number')->first();
            if(!$r||$r->status!=='active'||!$r->expires_at||$r->expires_at->isFuture())return;
            $r->status='expired';$r->saveOrFail();
            if($r->number)$r->number->update(['status'=>'available']);
            $count++;
        });
        return $count;
    }
}