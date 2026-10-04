<?php
namespace App\Services\Vtu;
use App\Models\ServiceProduct;
use App\Models\VtuBulkOperation;
use Illuminate\Support\Str;
class VtuBulkService{
 public function __construct(private VtuTransactionService $transactions,private VtuPayloadValidator $validator){}
 public function execute(int $uid,array $items,string $tier='USER'):VtuBulkOperation{$b=VtuBulkOperation::create(['uuid'=>(string)Str::uuid(),'reference'=>'BULK-'.strtoupper(Str::random(20)),'user_id'=>$uid,'status'=>'processing','total_items'=>count($items)]);foreach(array_values($items) as $i=>$item){$key=(string)($item['idempotency_key']??($b->reference.':'.($i+1)));$row=$b->items()->create(['sequence'=>$i+1,'idempotency_key'=>$key,'recipient'=>$item['payload']['recipient']??$item['payload']['phone']??null,'product_id'=>$item['product_id'],'status'=>'processing']);try{$p=ServiceProduct::query()->with('service')->findOrFail((int)$item['product_id']);$this->validator->validate($p->service,(array)$item['payload']);$tx=$this->transactions->process($this->transactions->create($uid,$p,(array)$item['payload'],$tier,$key));$row->update(['vtu_transaction_id'=>$tx->id,'status'=>$tx->status,'error_message'=>$tx->failure_message]);$b->increment($tx->status==='successful'?'successful_items':'failed_items');}catch(\Throwable $e){$row->update(['status'=>'failed','error_message'=>$e->getMessage()]);$b->increment('failed_items');}$b->increment('processed_items');}$b->refresh();$b->status=$b->failed_items>0?($b->successful_items>0?'partial':'failed'):'successful';$b->save();return $b->load('items');}
}