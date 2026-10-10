<?php
namespace Addons\CommunicationWhatsapp\Services;

use Addons\CommunicationWhatsapp\Models\Consent;
use RuntimeException;

class CommunicationConsentService
{
 public function allowed(int $userId,string $channel,string $purpose='marketing'): bool
 {
  if($purpose==='transactional') return true;
  $consent=Consent::where('user_id',$userId)->where('channel',$channel)->where('purpose',$purpose)->first();
  return $consent?->opted_in === true;
 }

 public function requireConsent(int $userId,string $channel,string $purpose='marketing'): void
 {
  if(!$this->allowed($userId,$channel,$purpose)) throw new RuntimeException('User has not opted in to this communication channel.');
 }

 public function set(int $userId,string $channel,string $purpose,bool $optedIn,?string $source=null): Consent
 {
  return Consent::updateOrCreate(
   ['user_id'=>$userId,'channel'=>$channel,'purpose'=>$purpose],
   ['opted_in'=>$optedIn,'source'=>$source,'consented_at'=>$optedIn?now():null,'revoked_at'=>$optedIn?null:now()]
  );
 }
}
