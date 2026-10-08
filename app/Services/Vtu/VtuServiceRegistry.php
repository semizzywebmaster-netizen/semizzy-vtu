<?php

namespace App\Services\Vtu;

use App\Models\Service;
use App\Models\ServiceCategory;
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
