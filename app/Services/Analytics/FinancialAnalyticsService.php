<?php
namespace App\Services\Analytics;
use App\Models\VtuTransaction;
use App\Models\WalletMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class FinancialAnalyticsService {
 public function summarize(int $userId, ?string $from=null, ?string $to=null): array {
  [$start,$end]=$this->range($from,$to);
  $tx=VtuTransaction::query()->where('user_id',$userId)->whereBetween('created_at',[$start,$end]);
  $successful=(clone $tx)->where('status','successful');
  $spent=(int)$successful->sum(DB::raw('CAST(total_minor AS UNSIGNED)'));
  $fees=(int)$successful->sum(DB::raw('CAST(fee_minor AS UNSIGNED)'));
  $count=(int)(clone $successful)->count();
  $average=$count?intdiv($spent,$count):0;
  $highest=(int)((clone $successful)->max(DB::raw('CAST(total_minor AS UNSIGNED)'))??0);
  $movements=WalletMovement::query()->whereHas('wallet',fn($q)=>$q->where('user_id',$userId))->whereBetween('created_at',[$start,$end])->get(['type','amount_minor']);
  $funded=0;$received=0;$transferred=0;$refunds=0;
  foreach($movements as $m){$type=strtolower((string)$m->type);$amount=(int)$m->amount_minor;
   if(str_contains($type,'refund')||str_contains($type,'reversal'))$refunds+=$amount;
   elseif(str_contains($type,'fund')||str_contains($type,'deposit')||str_contains($type,'credit'))$funded+=$amount;
   elseif(str_contains($type,'receive'))$received+=$amount;
   elseif(str_contains($type,'transfer')||str_contains($type,'send'))$transferred+=$amount;
  }
  $categoryRows=(clone $successful)->with('service.category')->get(['id','service_id','total_minor'])
   ->groupBy(fn($t)=>$t->service?->category?->name?:'Other')
   ->map(fn($rows,$name)=>['category'=>$name,'amount_minor'=>(int)$rows->sum(fn($t)=>(int)$t->total_minor),'count'=>$rows->count()])
   ->sortByDesc('amount_minor')->values()->all();
  $daily=(clone $successful)->selectRaw('DATE(created_at) as day, SUM(CAST(total_minor AS UNSIGNED)) as amount_minor, COUNT(*) as count')
   ->groupBy('day')->orderBy('day')->get()->map(fn($r)=>['date'=>Carbon::parse($r->day)->format('Y-m-d'),'amount_minor'=>(int)$r->amount_minor,'count'=>(int)$r->count])->values()->all();
  return ['from'=>$start->toDateString(),'to'=>$end->toDateString(),'currency'=>'NGN','total_spent_minor'=>$spent,'total_funded_minor'=>$funded,'total_received_minor'=>$received,'total_transferred_minor'=>$transferred,'total_fees_minor'=>$fees,'total_refunds_minor'=>$refunds,'transaction_count'=>$count,'average_transaction_minor'=>$average,'highest_transaction_minor'=>$highest,'categories'=>$categoryRows,'daily'=>$daily];
 }
 public function exportRows(int $userId,?string $from=null,?string $to=null):array{
  [$start,$end]=$this->range($from,$to);
  return VtuTransaction::query()->where('user_id',$userId)->whereBetween('created_at',[$start,$end])->with('service.category')->orderBy('created_at')->get()
   ->map(fn($t)=>['date'=>$t->created_at?->toDateTimeString(),'reference'=>$t->reference,'status'=>$t->status,'category'=>$t->service?->category?->name?:'Other','amount_minor'=>(string)$t->amount_minor,'fee_minor'=>(string)$t->fee_minor,'total_minor'=>(string)$t->total_minor,'currency'=>$t->currency])->all();
 }
 private function range(?string $from,?string $to):array{
  $end=$to?Carbon::parse($to)->endOfDay():now()->endOfDay();$start=$from?Carbon::parse($from)->startOfDay():$end->copy()->subDays(29)->startOfDay();
  if($start->gt($end))[$start,$end]=[$end->copy()->startOfDay(),$start->copy()->endOfDay()];
  if($start->diffInDays($end)>366)$start=$end->copy()->subDays(366)->startOfDay();
  return[$start,$end];
 }
}