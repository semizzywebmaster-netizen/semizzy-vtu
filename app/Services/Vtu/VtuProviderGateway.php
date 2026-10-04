<?php
namespace App\Services\Vtu;
use App\Models\VtuTransaction;
use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;
class VtuProviderGateway{
 public function __construct(private ProviderManager $providers){}
 public function initiate(VtuTransaction $tx,array $payload):ProviderResult{$ps=$this->providers->eligible($tx->service->key,'transaction_initiation');if($ps->isEmpty())return new ProviderResult(false,'FAILED',message:'No verified provider is eligible for this service.');$first=$ps->first(fn($p)=>(int)$p->id===(int)$tx->api_provider_id)??$ps->first();$r=$this->providers->executeProvider($first,$tx->service->key,'transaction_initiation',$payload,$tx->idempotency_key);if($r->accepted||$r->duplicateRisk||$r->status==='UNKNOWN'||$r->status==='PENDING')return $r;foreach($ps as $p){if($p->id===$first->id)continue;$r=$this->providers->executeProvider($p,$tx->service->key,'transaction_initiation',$payload,$tx->idempotency_key);if($r->accepted||$r->duplicateRisk||$r->status==='UNKNOWN'||$r->status==='PENDING')return $r;}return $r;}
 public function requery(VtuTransaction $tx):ProviderResult{if(!$tx->provider_reference)return new ProviderResult(false,'UNKNOWN',message:'No provider reference is available for requery.');return $this->providers->execute($tx->service->key,'transaction_status',['reference'=>$tx->provider_reference,'transaction_reference'=>$tx->reference],$tx->idempotency_key.':requery');}
}