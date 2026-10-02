<?php

namespace App\\Services\\Finance;

use App\\Models\\LedgerTransaction;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Str;
use RuntimeException;

class LedgerService
{
    public function post(string $reference, string $type, string $currency, array $entries, ?string $description = null, array $metadata = []): LedgerTransaction
    {
        abort_unless(config('semizzy.finance_enabled'), 503, 'Finance core is disabled.');
        return DB::transaction(function () use ($reference,$type,$currency,$entries,$description,$metadata) {
            $existing=LedgerTransaction::query()->where('reference',$reference)->lockForUpdate()->first();
            if($existing) return $existing;

            if(count($entries)<2) throw new RuntimeException('A ledger transaction requires at least two entries.');
            $debit='0'; $credit='0';
            foreach($entries as $entry){
                $d=(string)($entry['debit_minor']??'0'); $c=(string)($entry['credit_minor']??'0');
                if(($d==='0') === ($c==='0')) throw new RuntimeException('Each ledger entry must contain exactly one side.');
                if(str_contains($d,'-') || str_contains($c,'-')) throw new RuntimeException('Ledger amounts cannot be negative.');
                $debit=$this->add($debit,$d); $credit=$this->add($credit,$c);
            }
            if($debit !== $credit) throw new RuntimeException('Unbalanced ledger transaction.');
            $tx=LedgerTransaction::create(['uuid'=>(string)Str::uuid(),'reference'=>$reference,'type'=>$type,'status'=>'posted','currency'=>$currency,'description'=>$description,'metadata'=>$metadata]);
            foreach($entries as $entry) $tx->entries()->create(['ledger_account_id'=>$entry['ledger_account_id'],'debit_minor'=>$entry['debit_minor']??'0','credit_minor'=>$entry['credit_minor']??'0']);
            return $tx->load('entries');
        });
    }

    private function add(string $a,string $b): string
    {
        if(function_exists('bcadd')) return bcadd($a,$b,0);
        $a=ltrim($a,'0')?:'0'; $b=ltrim($b,'0')?:'0'; $carry=0; $out='';
        for($i=0,$j=0;$i<strlen($a)||$j<strlen($b);$i++,$j++){ $sum=($i<strlen($a)?ord($a[strlen($a)-1-$i])-48:0)+($j<strlen($b)?ord($b[strlen($b)-1-$j])-48:0)+$carry; $out=($sum%10).$out; $carry=intdiv($sum,10); }
        return $carry.$out === '0'.$out ? $out : $carry.$out;
    }
}
