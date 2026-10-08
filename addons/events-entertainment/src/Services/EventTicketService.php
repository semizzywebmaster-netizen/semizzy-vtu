<?php
namespace Semizzy\Addons\EventsEntertainment\Services;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Semizzy\Addons\EventsEntertainment\Models\{EventOrder,EventOrderItem,EventTicket,EventTicketType};
final class EventTicketService{
 public function createPendingOrder(?int $userId,EventTicketType $type,int $quantity,array $attendee=[]):EventOrder{
  return DB::transaction(function()use($userId,$type,$quantity,$attendee){$type=EventTicketType::query()->lockForUpdate()->findOrFail($type->id);if(!$type->is_active)throw new \RuntimeException('Ticket type is inactive.');if($type->quantity!==null && ($type->quantity-$type->quantity_sold)<$quantity)throw new \RuntimeException('Not enough tickets available.');if($quantity<$type->per_order_min||($type->per_order_max!==null&&$quantity>$type->per_order_max))throw new \RuntimeException('Ticket quantity is outside the allowed order limits.');$subtotal=(float)$type->price*$quantity;$order=EventOrder::create(['user_id'=>$userId,'event_id'=>$type->event_id,'order_number'=>'EVT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),'status'=>'pending','currency'=>'NGN','subtotal'=>$subtotal,'total'=>$subtotal]);EventOrderItem::create(['order_id'=>$order->id,'ticket_type_id'=>$type->id,'occurrence_id'=>$type->occurrence_id,'quantity'=>$quantity,'unit_price'=>$type->price,'line_total'=>$subtotal]);return $order;});
 }
 public function issuePaidOrder(EventOrder $order):void{
  DB::transaction(function()use($order){$order->load('items.ticketType');if($order->status!=='paid')throw new \RuntimeException('Only paid orders can issue tickets.');foreach($order->items as $item){$type=EventTicketType::query()->lockForUpdate()->findOrFail($item->ticket_type_id);if($type->quantity!==null&&($type->quantity-$type->quantity_sold)<$item->quantity)throw new \RuntimeException('Ticket inventory changed; insufficient inventory.');for($i=0;$i<$item->quantity;$i++){EventTicket::create(['order_item_id'=>$item->id,'event_id'=>$order->event_id,'ticket_type_id'=>$type->id,'occurrence_id'=>$item->occurrence_id,'user_id'=>$order->user_id,'ticket_number'=>'TKT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(10)),'qr_token'=>hash('sha256',Str::uuid().Str::random(32)),'status'=>'issued']);}$type->increment('quantity_sold',$item->quantity);}});}
}
