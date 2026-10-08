<?php
namespace Semizzy\Addons\EventsEntertainment\Services;
use Illuminate\Support\Facades\DB;
use Semizzy\Addons\EventsEntertainment\Models\EventOrder;
final class EventPaymentService{
 public function confirmApi(EventOrder $order,string $provider,string $transactionId):void{DB::transaction(function()use($order,$provider,$transactionId){$o=EventOrder::query()->lockForUpdate()->findOrFail($order->id);if($o->status==='paid')return;if(!in_array($o->status,['pending','awaiting_payment'],true))throw new \RuntimeException('Order cannot be paid in its current state.');$o->update(['status'=>'paid','payment_mode'=>'api','payment_provider'=>$provider,'provider_transaction_id'=>$transactionId,'paid_at'=>now(),'payment_confirmed_at'=>now()]);});}
 public function confirmManual(EventOrder $order,int $adminId,string $reference,string $note):void{DB::transaction(function()use($order,$adminId,$reference,$note){$o=EventOrder::query()->lockForUpdate()->findOrFail($order->id);if($o->status==='paid')return;$o->update(['status'=>'paid','payment_mode'=>'manual','manual_reference'=>$reference,'manual_note'=>$note,'manual_recorded_by'=>$adminId,'paid_at'=>now(),'payment_confirmed_at'=>now()]);});}
}