<?php
namespace App\Services\Vtu;
use App\Models\Addon;
use App\Models\FinancialOperation;
use App\Models\ServiceProduct;
use App\Models\VtuTransaction;
use App\Models\VtuBulkOperationItem;
use App\Services\Audit\AuditLogger;
use App\Services\Pricing\PriceEngine;
use App\Services\Commercial\CommercialServiceRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
class VtuTransactionService{
 public function __construct(private PriceEngine $pricing,private VtuProviderGateway $gateway,private VtuWalletService $wallet,private AuditLogger $audit,private CommercialServiceRegistry $commercial){}
 public function quote(ServiceProduct $p,string $tier='USER'):array{
  $tier=$this->normalizeTier($tier);
  $quote=$this->pricing->quote($p,$tier);
  $details=$this->commercial->quoteDetails((int)auth()->id(),(string)$p->service?->key,(string)$p->key,$this->toMinor($quote['customer_price']));
  $quote['customer_price']=number_format(((int)$details['amount_minor'])/100,2,'.','');
  $quote['business_partner_id']=$details['partner_id']??null;
  return $quote;
 }
 public function normalizeTier(string $role): string { $tier=strtoupper(trim($role)); return in_array($tier,['USER','AGENT','RESELLER','MERCHANT','CUSTOM'],true) ? $tier : 'USER'; }
 public function create(int $uid,ServiceProduct $p,array $payload,string $tier='USER',?string $key=null):VtuTransaction{$p->loadMissing('service.category');if(!$p->service||$p->service->category?->key!=='vtu-digital-services')throw new RuntimeException('Only products belonging to the active VTU & Digital Services addon can be transacted.');if(!Addon::query()->where('identifier','vtu.digital-services')->where('status','active')->exists())throw new RuntimeException('The VTU & Digital Services addon is not active.');$key=$key?:'vtu_'.Str::uuid();return DB::transaction(function()use($uid,$p,$payload,$tier,$key){$existing=VtuTransaction::query()->where('idempotency_key',$key)->lockForUpdate()->first();if($existing){if((int)$existing->user_id!==$uid||(int)$existing->service_product_id!==(int)$p->id||$existing->request_payload!==$payload)throw new RuntimeException('Idempotency key has already been used for a different transaction.');return $existing;}$normalizedTier=$this->normalizeTier($tier);$q=$this->pricing->quote($p,$normalizedTier);$baseMinor=$this->toMinor($q['customer_price']);$details=$this->commercial->quoteDetails($uid,(string)$p->service?->key,(string)$p->key,$baseMinor);$minor=(int)$details['amount_minor'];$this->commercial->authorize($uid,(string)$p->service?->key,(string)$p->key,$minor);$ref='VTU-'.strtoupper(Str::random(20));$tx=VtuTransaction::create(['uuid'=>(string)Str::uuid(),'reference'=>$ref,'user_id'=>$uid,'service_id'=>$p->service_id,'service_product_id'=>$p->id,'api_provider_id'=>$q['provider_id'],'idempotency_key'=>$key,'status'=>'pending','amount_minor'=>$minor,'fee_minor'=>'0','total_minor'=>$minor,'currency'=>$q['currency'],'customer_tier'=>$normalizedTier,'recipient'=>$this->recipient($payload),'request_payload'=>$payload,'metadata'=>['price_rule_id'=>$q['rule_id'],'business_partner_id'=>$details['partner_id']??null,'base_customer_price_minor'=>$baseMinor,'commercial_price_rule_id'=>$details['rule_id']??null]]);$op=FinancialOperation::create(['uuid'=>(string)Str::uuid(),'reference'=>$ref,'user_id'=>$uid,'type'=>'vtu.purchase','status'=>'pending','amount_minor'=>$minor,'currency'=>$q['currency'],'idempotency_key'=>$key,'metadata'=>['vtu_transaction_id'=>$tx->id]]);$tx->financial_operation_id=$op->id;$tx->save();$this->wallet->reserve($tx);$op->status='processing';$op->save();$tx->status='processing';$tx->processed_at=now();$tx->save();return $tx->fresh();});}
 private function partnerForTier(?int $userId,string $tier):?BusinessPartner{
  if(!$userId || !in_array($tier,['AGENT','MERCHANT','RESELLER'],true)) return null;
  return BusinessPartner::query()->where('user_id',$userId)->where('type',strtolower($tier))->where('status','active')->latest('id')->first();
 }

