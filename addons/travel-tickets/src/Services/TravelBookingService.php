<?php
namespace Semizzy\Addons\TravelTickets\Services;
use Illuminate\Support\Facades\DB; use Semizzy\Addons\TravelTickets\Models\TravelBooking; use Semizzy\Addons\TravelTickets\Models\TravelService;
class TravelBookingService {
 public function createBooking(int $userId,int $serviceId,string $type,array $payload,string $idempotencyKey):TravelBooking {
  $key=trim($idempotencyKey);if($key==='')throw new \InvalidArgumentException('Idempotency key is required.');
  $existing=TravelBooking::where('idempotency_key',$key)->first();if($existing)return $existing;
  $service=TravelService::whereKey($serviceId)->where('enabled',true)->firstOrFail();if($service->type!==$type)throw new \InvalidArgumentException('Travel service type mismatch.');
  $amount=(float)($payload['amount']??0);$fee=(float)($payload['fee']??0);if($amount<0||$fee<0)throw new \InvalidArgumentException('Invalid booking amount.');
  return DB::transaction(fn()=>TravelBooking::create(['user_id'=>$userId,'travel_service_id'=>$service->id,'type'=>$type,'status'=>'pending','idempotency_key'=>$key,'amount'=>$amount,'fee'=>$fee,'total'=>$amount+$fee,'currency'=>$payload['currency']??'NGN','search_data'=>$payload['search_data']??null,'passengers'=>$payload['passengers']??null,'booking_data'=>$payload['booking_data']??null]));
 }
 public function markConfirmed(TravelBooking $booking,?string $providerReference,?string $bookingReference,array $data=[]):TravelBooking{$booking->update(['status'=>'confirmed','provider_reference'=>$providerReference,'booking_reference'=>$bookingReference,'booking_data'=>array_merge($booking->booking_data??[],$data),'confirmed_at'=>now()]);return $booking->refresh();}
 public function markFailed(TravelBooking $booking,string $reason):TravelBooking{$booking->update(['status'=>'failed','failure_reason'=>$reason]);return $booking->refresh();}
}