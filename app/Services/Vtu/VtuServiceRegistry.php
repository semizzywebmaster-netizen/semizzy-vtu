<?php

namespace App\Services\Vtu;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ApiProvider;
use App\Models\VtuTransaction;
use Illuminate\Support\Collection;

class VtuServiceRegistry
{
    public const MANIFEST = [
        'airtime' => 'Airtime',
        'data' => 'Data',
        'airtime_to_cash' => 'Airtime to Cash',
        'airtime_to_data' => 'Airtime to Data',
        'data_to_cash' => 'Data to Cash',
        'data_to_airtime' => 'Data to Airtime',
        'recharge_pin' => 'Recharge PIN / ePIN',
        'electricity' => 'Electricity',
        'cable_tv' => 'Cable TV',
        'broadband' => 'Broadband / ISP',
        'international_topup' => 'International Top-up',
        'bulk_airtime' => 'Bulk Airtime',
        'bulk_data' => 'Bulk Data',
        'bulk_sms' => 'Bulk SMS',
        'education' => 'Education / Exam Services',
        'betting_funding' => 'Betting / Gaming Funding',
    ];

    public function services(): Collection
    {
        return Service::query()
            ->whereIn('key', array_keys(self::MANIFEST))
            ->where('enabled', true)
            ->with(['category', 'products' => fn ($q) => $q->where('enabled', true)->orderBy('name')])
            ->orderBy('id')
            ->get();
    }


    public function health(Service $service): array
    {
        if (str_contains((string) $service->key, '_to_')) {
            return ['mode'=>'manual','status'=>'healthy','label'=>'100% success','success_rate'=>100.0,'sample_size'=>0,'window_hours'=>24,'providers'=>[]];
        }
        $rows = VtuTransaction::query()->where('service_id',$service->id)->where('created_at','>=',now()->subHours(24))->whereIn('status',['successful','failed'])->get(['status','api_provider_id']);
        $total=$rows->count(); $successful=$rows->where('status','successful')->count(); $rate=$total>0?round(($successful/$total)*100,2):null;
        $providerIds=$rows->pluck('api_provider_id')->filter()->unique()->values();
        $providerNames=$providerIds->isEmpty()?collect():ApiProvider::query()->whereIn('id',$providerIds)->pluck('display_name','id');
        $providers=$rows->groupBy('api_provider_id')->map(function($group,$providerId)use($providerNames){$n=$group->count();$s=$group->where('status','successful')->count();return ['provider_id'=>$providerId?(int)$providerId:null,'provider'=>$providerNames->get($providerId,'Unknown provider'),'success_rate'=>$n?round(($s/$n)*100,2):0.0,'sample_size'=>$n];})->values()->all();
        return ['mode'=>'provider','status'=>$rate===null?'unknown':($rate>=98?'healthy':($rate>=90?'degraded':'down')),'label'=>$rate===null?'No recent data':number_format($rate,2).'% success','success_rate'=>$rate,'sample_size'=>$total,'window_hours'=>24,'providers'=>$providers];
    }

    public function conversionSettings(): array
    {
        $keys=['airtime_to_cash','airtime_to_data','data_to_cash','data_to_airtime'];
        $services=Service::query()->whereIn('key',$keys)->pluck('id','key');
        $rows=\DB::table('vtu_service_configurations')->whereIn('service_id',$services->values())->whereIn('key',['conversion_rate_percent','conversion_fee_minor'])->get();
        return collect($keys)->mapWithKeys(function($key)use($services,$rows){$id=$services->get($key);$rateRow=$rows->first(fn($r)=>(int)$r->service_id===(int)$id&&$r->key==='conversion_rate_percent');$feeRow=$rows->first(fn($r)=>(int)$r->service_id===(int)$id&&$r->key==='conversion_fee_minor');$rate=$rateRow?(float)data_get(json_decode($rateRow->value,true),'value',0):0.0;$fee=$feeRow?(string)data_get(json_decode($feeRow->value,true),'value','0'):'0';return [$key=>['label'=>self::MANIFEST[$key]??$key,'rate_percent'=>$rate>0?$rate:null,'fee_minor'=>$fee,'configured'=>$rate>0]];})->all();
    }

    public function saveConversionSettings(array $settings): void
    {
        $keys=['airtime_to_cash','airtime_to_data','data_to_cash','data_to_airtime'];
        $services=Service::query()->whereIn('key',$keys)->pluck('id','key');
        foreach($keys as $key){$serviceId=$services->get($key);if(!$serviceId)continue;$rate=(float)($settings[$key]['rate_percent']??0);$fee=(float)($settings[$key]['fee']??0);if($rate<=0||$rate>100)throw new \RuntimeException("Conversion rate for {$key} must be greater than 0 and at most 100%.");if($fee<0)throw new \RuntimeException("Conversion fee for {$key} cannot be negative.");$feeMinor=(string)round($fee*100);foreach([['conversion_rate_percent',['value'=>$rate]],['conversion_fee_minor',['value'=>$feeMinor]]] as [$settingKey,$value]){\DB::table('vtu_service_configurations')->updateOrInsert(['service_id'=>$serviceId,'key'=>$settingKey],['value'=>json_encode($value),'is_secret'=>false,'enabled'=>true,'updated_at'=>now(),'created_at'=>now()]);}}
    }

    public function bootstrapCatalogue(): void
    {
        $category = ServiceCategory::query()->updateOrCreate(
            ['key' => 'vtu-digital-services'],
            [
                'name' => 'VTU & Digital Services',
                'description' => 'Provider-driven digital services plus manually verified airtime/data conversion.',
                'enabled' => true,
                'sort_order' => 20,
            ]
        );

        foreach (self::MANIFEST as $key => $name) {
            $service = Service::query()->firstOrNew(['key' => $key]);
            $wasExisting = $service->exists;
            $service->category_id = $category->id;
            $service->name = $name;
            $service->description = $name . ' service powered by the SEMIZZY ONE service engine.';
            $service->metadata = [
                ...((array) $service->metadata),
                'addon' => 'vtu.digital-services',
                'service_type' => $key,
                'execution_mode' => str_contains($key, '_to_') ? 'manual_conversion' : 'provider_or_manual',
                'required_fields' => $this->fields($key),
            ];
            if (! $wasExisting) {
                $service->enabled = false;
            }
            $service->save();
        }
    }

    public function fields(string $key): array
    {
        return match ($key) {
            'airtime' => ['network', 'phone', 'amount'],
            'data' => ['network', 'phone', 'plan'],
            'airtime_to_cash' => ['network', 'source_phone', 'amount', 'proof'],
            'airtime_to_data' => ['network', 'source_phone', 'amount', 'target_phone', 'data_plan', 'proof'],
            'data_to_cash' => ['network', 'source_phone', 'product', 'quantity', 'proof'],
            'data_to_airtime' => ['network', 'source_phone', 'product', 'quantity', 'target_phone', 'proof'],
            'recharge_pin' => ['network', 'product', 'quantity'],
            'electricity' => ['disco', 'meter_number', 'meter_type', 'amount'],
            'cable_tv' => ['provider', 'customer_number', 'package'],
            'broadband' => ['provider', 'account_id', 'package'],
            'international_topup' => ['country', 'operator', 'product', 'recipient', 'amount'],
            'bulk_airtime' => ['recipients'],
            'bulk_data' => ['recipients', 'plan'],
            'bulk_sms' => ['recipients', 'message', 'sender_id'],
            'education' => ['exam', 'product', 'candidate'],
            'betting_funding' => ['provider', 'account_id', 'amount'],
            default => [],
        };
    }
}