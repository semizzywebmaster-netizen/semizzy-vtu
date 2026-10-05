<?php

namespace App\Services\Providers;

use App\Models\ApiProvider;
use App\Models\ProviderServiceMapping;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Facades\DB;

final class ProviderPresetRegistry
{
    public function install(): array
    {
        return DB::transaction(function (): array {
            $services = $this->services();
            $categoryIds = [];

            foreach ($this->categories() as $category) {
                $row = ServiceCategory::query()->updateOrCreate(
                    ['key' => $category['key']],
                    $category
                );
                $categoryIds[$row->key] = $row->id;
            }

            $serviceIds = [];
            foreach ($services as $service) {
                $row = Service::query()->updateOrCreate(
                    ['key' => $service['key']],
                    [
                        'category_id' => $categoryIds[$service['category_key']],
                        'name' => $service['name'],
                        'description' => $service['description'],
                        'metadata' => $service['metadata'] ?? [],
                        'enabled' => true,
                    ]
                );
                $serviceIds[$row->key] = $row->id;
            }

            $providerCount = 0;
            $mappingCount = 0;

            foreach ($this->providers() as $definition) {
                $provider = ApiProvider::withTrashed()->where('identifier', $definition['identifier'])->first();

                if ($provider) {
                    if ($provider->trashed()) {
                        $provider->restore();
                    }

                    // Presets may update non-secret configuration, but never overwrite
                    // credentials already entered by the administrator.
                    $provider->fill([
                        'display_name' => $definition['display_name'],
                        'official_website' => $definition['official_website'],
                        'documentation_url' => $definition['documentation_url'],
                        'service_categories' => $definition['service_categories'],
                        'capabilities' => $definition['capabilities'],
                        'endpoints' => $definition['endpoints'],
                        'api_version' => $definition['api_version'] ?? null,
                        'auth_type' => $definition['auth_type'],
                        'environment' => 'sandbox',
                        'base_url' => $definition['base_url'],
                        'priority' => $definition['priority'] ?? 100,
                    ])->save();
                } else {
                    $provider = ApiProvider::create([
                        ...$definition,
                        'environment' => 'sandbox',
                        'credentials' => [],
                        'enabled' => false,
                        'paused' => true,
                        'verification_status' => 'unverified',
                        'integration_status' => 'draft',
                        'priority' => $definition['priority'] ?? 100,
                    ]);
                }

                $providerCount++;

                foreach ($definition['mappings'] as $mapping) {
                    if (! isset($serviceIds[$mapping['service_key']])) {
                        continue;
                    }

                    ProviderServiceMapping::query()->updateOrCreate(
                        [
                            'api_provider_id' => $provider->id,
                            'service_id' => $serviceIds[$mapping['service_key']],
                        ],
                        [
                            'service_key' => $mapping['service_key'],
                            'provider_service_id' => $mapping['provider_service_id'] ?? $mapping['service_key'],
                            'capabilities' => $mapping['capabilities'] ?? [],
                            'enabled' => false,
                        ]
                    );

                    $mappingCount++;
                }
            }

            return [
                'providers' => $providerCount,
                'services' => count($serviceIds),
                'mappings' => $mappingCount,
            ];
        });
    }

    private function categories(): array
    {
        return [
            ['key' => 'airtime', 'name' => 'Airtime', 'description' => 'Mobile airtime recharge services.', 'enabled' => true, 'sort_order' => 10],
            ['key' => 'data', 'name' => 'Data', 'description' => 'Mobile data bundles and internet services.', 'enabled' => true, 'sort_order' => 20],
            ['key' => 'electricity', 'name' => 'Electricity', 'description' => 'Electricity bill and meter services.', 'enabled' => true, 'sort_order' => 30],
            ['key' => 'cable-tv', 'name' => 'Cable TV', 'description' => 'Digital TV subscription services.', 'enabled' => true, 'sort_order' => 40],
            ['key' => 'education', 'name' => 'Education', 'description' => 'Education PINs and related services.', 'enabled' => true, 'sort_order' => 50],
            ['key' => 'payments', 'name' => 'Payments', 'description' => 'Payment collection and checkout services.', 'enabled' => true, 'sort_order' => 60],
            ['key' => 'transfers', 'name' => 'Transfers', 'description' => 'Bank and payout transfer services.', 'enabled' => true, 'sort_order' => 70],
            ['key' => 'verification', 'name' => 'Verification', 'description' => 'Identity and account verification services.', 'enabled' => true, 'sort_order' => 80],
            ['key' => 'messaging', 'name' => 'Messaging', 'description' => 'SMS and OTP messaging services.', 'enabled' => true, 'sort_order' => 90],
            ['key' => 'utilities', 'name' => 'Utilities', 'description' => 'General bill and utility services.', 'enabled' => true, 'sort_order' => 100],
        ];
    }

    private function services(): array
    {
        return [
            ['key'=>'airtime', 'category_key'=>'airtime', 'name'=>'Airtime Recharge', 'description'=>'Airtime top-up across supported mobile networks.', 'metadata'=>['provider_catalogue_supported'=>true,'catalogue_request'=>['identifier'=>'airtime']]],
            ['key'=>'data', 'category_key'=>'data', 'name'=>'Mobile Data Bundles', 'description'=>'Mobile data bundles across supported networks.', 'metadata'=>['provider_catalogue_supported'=>true,'catalogue_request'=>['identifier'=>'data']]],
            ['key'=>'electricity', 'category_key'=>'electricity', 'name'=>'Electricity Bills', 'description'=>'Electricity prepaid and postpaid bill payments.', 'metadata'=>['provider_catalogue_supported'=>true,'catalogue_request'=>['identifier'=>'electricity-bill']]],
            ['key'=>'cable-tv', 'category_key'=>'cable-tv', 'name'=>'Cable TV Subscription', 'description'=>'DSTV, GOtv, Startimes and other supported TV services.', 'metadata'=>['provider_catalogue_supported'=>true,'catalogue_request'=>['identifier'=>'tv-subscription']]],
            ['key'=>'education', 'category_key'=>'education', 'name'=>'Education Payments', 'description'=>'WAEC and other supported education products.', 'metadata'=>['provider_catalogue_supported'=>true,'catalogue_request'=>['identifier'=>'education']]],
            ['key'=>'payment-collection', 'category_key'=>'payments', 'name'=>'Payment Collection', 'description'=>'Online payment collection and checkout.', 'metadata'=>['provider_catalogue_supported'=>false]],
            ['key'=>'bank-transfer', 'category_key'=>'transfers', 'name'=>'Bank Transfers', 'description'=>'Payouts and bank transfers.', 'metadata'=>['provider_catalogue_supported'=>false]],
            ['key'=>'account-verification', 'category_key'=>'verification', 'name'=>'Bank/Account Verification', 'description'=>'Bank account and customer verification.', 'metadata'=>['provider_catalogue_supported'=>false]],
            ['key'=>'kyc-verification', 'category_key'=>'verification', 'name'=>'Identity/KYC Verification', 'description'=>'Identity verification and KYC checks.', 'metadata'=>['provider_catalogue_supported'=>false]],
            ['key'=>'sms', 'category_key'=>'messaging', 'name'=>'SMS Messaging', 'description'=>'Transactional and bulk SMS.', 'metadata'=>['provider_catalogue_supported'=>false]],
            ['key'=>'otp', 'category_key'=>'messaging', 'name'=>'OTP Verification', 'description'=>'OTP generation and verification messaging.', 'metadata'=>['provider_catalogue_supported'=>false]],
            ['key'=>'utility-bills', 'category_key'=>'utilities', 'name'=>'Utility Bills', 'description'=>'Utility and bill payment aggregation.', 'metadata'=>['provider_catalogue_supported'=>true]],
        ];
    }

    private function providers(): array
    {
        return [
            [
                'identifier'=>'vtpass',
                'display_name'=>'VTpass',
                'base_url'=>'https://vtpass.com/api',
                'documentation_url'=>'https://vtpass.com/documentation/',
                'official_website'=>'https://vtpass.com/',
                'auth_type'=>'custom',
                'service_categories'=>['airtime','data','electricity','tv-subscription','education','other-services','insurance','sms'],
                'capabilities'=>['health','balance','catalogue','transactions','status'],
                'endpoints'=>[
                    'health_check'=>'/service-categories',
                    'catalogue_retrieval'=>'/services',
                    'transaction_initiation'=>'/pay',
                    'transaction_status'=>'/requery',
                ],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['purchase','status','catalogue']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['purchase','status','catalogue']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity-bill','capabilities'=>['purchase','status','catalogue']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'tv-subscription','capabilities'=>['purchase','status','catalogue']],
                    ['service_key'=>'education','provider_service_id'=>'education','capabilities'=>['purchase','status','catalogue']],
                    ['service_key'=>'utility-bills','provider_service_id'=>'other-services','capabilities'=>['purchase','status','catalogue']],
                ],
                'priority'=>10,
            ],
            [
                'identifier'=>'reloadly',
                'display_name'=>'Reloadly',
                'base_url'=>'https://topups.reloadly.com',
                'documentation_url'=>'https://www.reloadly.com/developers',
                'official_website'=>'https://www.reloadly.com/',
                'auth_type'=>'custom',
                'service_categories'=>['airtime','data'],
                'capabilities'=>['health','catalogue','transactions','status'],
                'endpoints'=>[
                    'catalogue_retrieval'=>'/operators',
                    'transaction_initiation'=>'/topups',
                    'transaction_status'=>'/transactions',
                ],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['purchase','status','catalogue']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['purchase','status','catalogue']],
                ],
                'priority'=>20,
            ],
            [
                'identifier'=>'monnify',
                'display_name'=>'Monnify',
                'base_url'=>'https://api.monnify.com',
                'documentation_url'=>'https://developers.monnify.com/',
                'official_website'=>'https://monnify.com/',
                'auth_type'=>'bearer',
                'service_categories'=>['payments','transfers','verification','utilities','airtime'],
                'capabilities'=>['health','payments','transfers','verification','bills'],
                'endpoints'=>[],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'payment-collection','provider_service_id'=>'payment-collection','capabilities'=>['payment']],
                    ['service_key'=>'bank-transfer','provider_service_id'=>'transfers','capabilities'=>['transfer']],
                    ['service_key'=>'account-verification','provider_service_id'=>'verification','capabilities'=>['verification']],
                    ['service_key'=>'utility-bills','provider_service_id'=>'bills','capabilities'=>['bills']],
                ],
                'priority'=>30,
            ],
            [
                'identifier'=>'paystack',
                'display_name'=>'Paystack',
                'base_url'=>'https://api.paystack.co',
                'documentation_url'=>'https://paystack.com/docs/api/',
                'official_website'=>'https://paystack.com/',
                'auth_type'=>'bearer',
                'service_categories'=>['payments','transfers','verification'],
                'capabilities'=>['health','payments','transfers','verification'],
                'endpoints'=>[],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'payment-collection','provider_service_id'=>'transactions','capabilities'=>['payment']],
                    ['service_key'=>'bank-transfer','provider_service_id'=>'transfers','capabilities'=>['transfer']],
                    ['service_key'=>'account-verification','provider_service_id'=>'bank','capabilities'=>['verification']],
                ],
                'priority'=>40,
            ],
            [
                'identifier'=>'flutterwave',
                'display_name'=>'Flutterwave',
                'base_url'=>'https://api.flutterwave.com/v3',
                'documentation_url'=>'https://developer.flutterwave.com/',
                'official_website'=>'https://flutterwave.com/',
                'auth_type'=>'bearer',
                'service_categories'=>['payments','transfers','verification','utilities'],
                'capabilities'=>['health','payments','transfers','verification','bills'],
                'endpoints'=>[],
                'api_version'=>'v3',
                'mappings'=>[
                    ['service_key'=>'payment-collection','provider_service_id'=>'payments','capabilities'=>['payment']],
                    ['service_key'=>'bank-transfer','provider_service_id'=>'transfers','capabilities'=>['transfer']],
                    ['service_key'=>'account-verification','provider_service_id'=>'banks','capabilities'=>['verification']],
                    ['service_key'=>'utility-bills','provider_service_id'=>'bill-payments','capabilities'=>['bills']],
                ],
                'priority'=>50,
            ],
            [
                'identifier'=>'termii',
                'display_name'=>'Termii',
                'base_url'=>'https://api.ng.termii.com',
                'documentation_url'=>'https://developers.termii.com/',
                'official_website'=>'https://termii.com/',
                'auth_type'=>'custom',
                'service_categories'=>['messaging'],
                'capabilities'=>['sms','otp','insights'],
                'endpoints'=>[
                    'transaction_initiation'=>'/api/sms/send',
                ],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'sms','provider_service_id'=>'messaging','capabilities'=>['sms']],
                    ['service_key'=>'otp','provider_service_id'=>'otp','capabilities'=>['otp']],
                ],
                'priority'=>60,
            ],
        ];
    }
}
