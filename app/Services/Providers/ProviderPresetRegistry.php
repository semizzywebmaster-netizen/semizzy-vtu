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
                $existingService = Service::query()->where('key', $service['key'])->first();
                $row = Service::query()->updateOrCreate(
                    ['key' => $service['key']],
                    [
                        'category_id' => $categoryIds[$service['category_key']],
                        'name' => $service['name'],
                        'description' => $service['description'],
                        'metadata' => $service['metadata'] ?? [],
                        'enabled' => $existingService ? (bool) $existingService->enabled : true,
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
                    // Refresh discovery metadata without overwriting administrator-managed
                    // connection settings. A preset reinstall must never silently change a
                    // configured production base URL, auth mode, endpoint map or capabilities.
                    $updates = [
                        'official_website' => $definition['official_website'] ?? $provider->official_website,
                        'documentation_url' => $definition['documentation_url'] ?? $provider->documentation_url,
                    ];

                    foreach (['display_name', 'service_categories', 'capabilities', 'endpoints', 'api_version', 'auth_type', 'base_url'] as $field) {
                        $current = $provider->getAttribute($field);
                        $empty = $current === null || $current === '' || $current === [];
                        if ($empty && array_key_exists($field, $definition)) {
                            $updates[$field] = $definition[$field];
                        }
                    }

                    $provider->fill($updates)->save();
                } else {
                    $providerData = $definition;
                    unset($providerData['mappings']);
                    $provider = ApiProvider::create([
                        ...$providerData,
                        'environment' => $providerData['environment'] ?? 'sandbox',
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
                            'enabled' => ProviderServiceMapping::query()
                                ->where('api_provider_id', $provider->id)
                                ->where('service_id', $serviceIds[$mapping['service_key']])
                                ->value('enabled') ?? false,
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
                'capabilities'=>['health_check','balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status'],
                'endpoints'=>[
                    'health_check'=>'/service-categories',
                    'catalogue_retrieval'=>'/services',
                    'transaction_initiation'=>'/pay',
                    'transaction_status'=>'/requery',
                ],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity-bill','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'tv-subscription','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'education','provider_service_id'=>'education','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'utility-bills','provider_service_id'=>'other-services','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
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
                'capabilities'=>['health_check','catalogue_retrieval','transaction_initiation','transaction_status'],
                'endpoints'=>[
                    'catalogue_retrieval'=>'/operators',
                    'transaction_initiation'=>'/topups',
                    'transaction_status'=>'/transactions',
                ],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                ],
                'priority'=>20,
            ],
            [
                'identifier'=>'vtung',
                'display_name'=>'VTU.ng',
                'base_url'=>'https://vtu.ng/wp-json',
                'documentation_url'=>'https://vtu.ng/api/',
                'official_website'=>'https://vtu.ng/',
                'auth_type'=>'bearer',
                'service_categories'=>['airtime','data','electricity','cable-tv','betting','education'],
                'capabilities'=>['balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status','webhook'],
                'endpoints'=>['catalogue_retrieval'=>'/api/v2/variations/data','transaction_status'=>'/api/v2/requery'],
                'api_version'=>'v2',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'tv','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                ],
                'priority'=>15,
            ],
            [
                'identifier'=>'iacafe',
                'display_name'=>'IA-Café',
                'base_url'=>'https://iacafe.com.ng/devapi/v1',
                'documentation_url'=>'https://iacafe.com.ng/developer',
                'official_website'=>'https://iacafe.com.ng/',
                'auth_type'=>'bearer',
                'service_categories'=>['airtime','data','electricity','cable-tv','utilities'],
                'capabilities'=>['balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status','webhook'],
                'endpoints'=>['catalogue_retrieval'=>'/variations','transaction_initiation'=>'/data','transaction_status'=>'/orders'],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'cable','capabilities'=>['transaction_initiation','transaction_status']],
                ],
                'priority'=>18,
            ],
            [
                'identifier'=>'clubkonnect',
                'display_name'=>'ClubKonnect',
                'base_url'=>'https://www.clubkonnect.com',
                'documentation_url'=>'https://www.clubkonnect.com/API',
                'official_website'=>'https://www.clubkonnect.com/',
                'auth_type'=>'custom',
                'service_categories'=>['airtime','data','electricity','cable-tv','education'],
                'capabilities'=>['balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status'],
                'endpoints'=>[],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'cable','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'education','provider_service_id'=>'education','capabilities'=>['transaction_initiation']],
                ],
                'priority'=>25,
            ],
            [
                'identifier'=>'rapidbills',
                'display_name'=>'RapidBills',
                'base_url'=>'https://rapidbills.ng',
                'documentation_url'=>'https://rapidbills.ng/',
                'official_website'=>'https://rapidbills.ng/',
                'auth_type'=>'bearer',
                'service_categories'=>['airtime','data','electricity','cable-tv'],
                'capabilities'=>['balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status'],
                'endpoints'=>[],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'cable','capabilities'=>['transaction_initiation','transaction_status']],
                ],
                'priority'=>28,
            ],
            [
                'identifier'=>'smeapi',
                'display_name'=>'SME API',
                'base_url'=>'https://smeapi.com.ng/api',
                'documentation_url'=>'https://smeapi.com.ng/apidocumentation.html',
                'official_website'=>'https://smeapi.com.ng/',
                'auth_type'=>'custom',
                'service_categories'=>['airtime','data','cable-tv','electricity','education'],
                'capabilities'=>['balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status'],
                'endpoints'=>[],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'cable','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'education','provider_service_id'=>'education','capabilities'=>['transaction_initiation']],
                ],
                'priority'=>32,
            ],
            [
                'identifier'=>'bigisub',
                'display_name'=>'Bigisub',
                'base_url'=>'https://bigisub.ng',
                'documentation_url'=>'https://bigisub.ng/landing/developers/',
                'official_website'=>'https://bigisub.ng/',
                'auth_type'=>'bearer',
                'service_categories'=>['airtime','data','cable-tv','electricity','education','messaging'],
                'capabilities'=>['balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status','webhook'],
                'endpoints'=>['transaction_initiation'=>'/api/v2/data/purchase'],
                'api_version'=>'v2',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'cable','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'education','provider_service_id'=>'education','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'sms','provider_service_id'=>'sms','capabilities'=>['transaction_initiation']],
                ],
                'priority'=>35,
            ],
            [
                'identifier'=>'husmodataapi',
                'display_name'=>'Husmodataapi',
                'base_url'=>'https://husmodataapi.com',
                'documentation_url'=>'https://husmodataapi.com/',
                'official_website'=>'https://husmodataapi.com/',
                'auth_type'=>'bearer',
                'service_categories'=>['airtime','data','cable-tv','electricity','messaging','education'],
                'capabilities'=>['balance_inquiry','catalogue_retrieval','transaction_initiation','transaction_status'],
                'endpoints'=>[],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>['transaction_initiation','transaction_status','catalogue_retrieval']],
                    ['service_key'=>'electricity','provider_service_id'=>'electricity','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'cable-tv','provider_service_id'=>'cable','capabilities'=>['transaction_initiation','transaction_status']],
                    ['service_key'=>'education','provider_service_id'=>'education','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'sms','provider_service_id'=>'sms','capabilities'=>['transaction_initiation']],
                ],
                'priority'=>22,
            ],
            [
                'identifier'=>'paybeta',
                'display_name'=>'Paybeta',
                'base_url'=>'https://paybeta.ng',
                'documentation_url'=>'https://docs.paybeta.ng/',
                'official_website'=>'https://paybeta.ng/',
                'auth_type'=>'custom',
                'service_categories'=>['airtime','data'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>[]],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>[]],
                ],
                'priority'=>40,
            ],
            [
                'identifier'=>'vtugate',
                'display_name'=>'VTUGATE',
                'base_url'=>'https://vtugate.com',
                'documentation_url'=>'https://vtugate.com/docs',
                'official_website'=>'https://vtugate.com/',
                'auth_type'=>'custom',
                'service_categories'=>['airtime','data'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>[]],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>[]],
                ],
                'priority'=>41,
            ],
            [
                'identifier'=>'otobill',
                'display_name'=>'OTOBILL',
                'base_url'=>'https://otobill.com',
                'documentation_url'=>'https://otobill.com/',
                'official_website'=>'https://otobill.com/',
                'auth_type'=>'custom',
                'service_categories'=>['airtime','data'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>[]],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>[]],
                ],
                'priority'=>42,
            ],
            [
                'identifier'=>'vtuagent',
                'display_name'=>'VTUAgent',
                'base_url'=>'https://vtuagent.com',
                'documentation_url'=>'https://docs.vtuagent.com/api-reference',
                'official_website'=>'https://vtuagent.com/',
                'auth_type'=>'custom',
                'service_categories'=>['airtime','data'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'airtime','provider_service_id'=>'airtime','capabilities'=>[]],
                    ['service_key'=>'data','provider_service_id'=>'data','capabilities'=>[]],
                ],
                'priority'=>43,
            ],
            [
                'identifier'=>'monnify',
                'display_name'=>'Monnify',
                'base_url'=>'https://api.monnify.com',
                'documentation_url'=>'https://developers.monnify.com/',
                'official_website'=>'https://monnify.com/',
                'auth_type'=>'bearer',
                'service_categories'=>['payments','transfers','verification','utilities','airtime'],
                'capabilities'=>['health_check','transaction_initiation','transaction_status'],
                'endpoints'=>[],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'payment-collection','provider_service_id'=>'payment-collection','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'bank-transfer','provider_service_id'=>'transfers','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'account-verification','provider_service_id'=>'verification','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'kyc-verification','provider_service_id'=>'bvn-nin-verification','capabilities'=>[]],
                    ['service_key'=>'utility-bills','provider_service_id'=>'bills','capabilities'=>['transaction_initiation']],
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
                'capabilities'=>['health_check','transaction_initiation','transaction_status'],
                'endpoints'=>[],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'payment-collection','provider_service_id'=>'transactions','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'bank-transfer','provider_service_id'=>'transfers','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'account-verification','provider_service_id'=>'bank','capabilities'=>['transaction_initiation']],
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
                'capabilities'=>['health_check','balance_inquiry','transaction_initiation','transaction_status'],
                'endpoints'=>[
                    'health_check'=>'/balances',
                    'balance_inquiry'=>'/balances',
                    'transaction_initiation'=>'/payments',
                    'transaction_status'=>'/transactions/verify_by_reference',
                ],
                'api_version'=>'v3',
                'mappings'=>[
                    ['service_key'=>'payment-collection','provider_service_id'=>'payments','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'bank-transfer','provider_service_id'=>'transfers','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'account-verification','provider_service_id'=>'banks','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'utility-bills','provider_service_id'=>'bill-payments','capabilities'=>['transaction_initiation']],
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
                'capabilities'=>['transaction_initiation','transaction_status'],
                'endpoints'=>[
                    'transaction_initiation'=>'/api/sms/send',
                ],
                'api_version'=>'v1',
                'mappings'=>[
                    ['service_key'=>'sms','provider_service_id'=>'messaging','capabilities'=>['transaction_initiation']],
                    ['service_key'=>'otp','provider_service_id'=>'otp','capabilities'=>['transaction_initiation']],
                ],
                'priority'=>60,
            ],
            [
                'identifier'=>'korapay',
                'display_name'=>'Kora',
                'base_url'=>'https://api.korapay.com/merchant/api/v1',
                'documentation_url'=>'https://developers.korapay.com/docs/accept-payments',
                'official_website'=>'https://korapay.com/',
                'auth_type'=>'custom',
                'service_categories'=>['payments','transfers','verification'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'payment-collection','provider_service_id'=>'payments','capabilities'=>[]],
                    ['service_key'=>'bank-transfer','provider_service_id'=>'payouts','capabilities'=>[]],
                    ['service_key'=>'account-verification','provider_service_id'=>'bank-account-verification','capabilities'=>[]],
                    ['service_key'=>'kyc-verification','provider_service_id'=>'identity-verification','capabilities'=>[]],
                ],
                'priority'=>70,
            ],
            [
                'identifier'=>'interswitch',
                'display_name'=>'Interswitch',
                'base_url'=>'https://api.interswitchng.com',
                'documentation_url'=>'https://developer.interswitchgroup.com/',
                'official_website'=>'https://interswitchgroup.com/',
                'auth_type'=>'custom',
                'service_categories'=>['payments','transfers','verification'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'payment-collection','provider_service_id'=>'payments','capabilities'=>[]],
                    ['service_key'=>'bank-transfer','provider_service_id'=>'send-money','capabilities'=>[]],
                    ['service_key'=>'account-verification','provider_service_id'=>'name-enquiry','capabilities'=>[]],
                    ['service_key'=>'kyc-verification','provider_service_id'=>'identity-verification','capabilities'=>[]],
                ],
                'priority'=>72,
            ],
            [
                'identifier'=>'dojah',
                'display_name'=>'Dojah',
                'base_url'=>'https://api.dojah.io/api/v1',
                'documentation_url'=>'https://docs.dojah.io/',
                'official_website'=>'https://dojah.io/',
                'auth_type'=>'custom',
                'service_categories'=>['verification'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'account-verification','provider_service_id'=>'resolve-nuban','capabilities'=>[]],
                    ['service_key'=>'kyc-verification','provider_service_id'=>'identity-verification','capabilities'=>[]],
                ],
                'priority'=>74,
            ],
            [
                'identifier'=>'prembly',
                'display_name'=>'Prembly',
                'base_url'=>'https://api.prembly.com',
                'documentation_url'=>'https://docs.prembly.com/',
                'official_website'=>'https://prembly.com/',
                'auth_type'=>'custom',
                'service_categories'=>['verification'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'kyc-verification','provider_service_id'=>'identity-verification','capabilities'=>[]],
                ],
                'priority'=>76,
            ],
            [
                'identifier'=>'sendchamp',
                'display_name'=>'Sendchamp',
                'base_url'=>'https://api.sendchamp.com/api/v1',
                'documentation_url'=>'https://sendchamp.readme.io/reference/send-otp-api',
                'official_website'=>'https://sendchamp.com/',
                'auth_type'=>'custom',
                'service_categories'=>['messaging'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'sms','provider_service_id'=>'sms','capabilities'=>[]],
                    ['service_key'=>'otp','provider_service_id'=>'otp-verification','capabilities'=>[]],
                ],
                'priority'=>78,
            ],
            [
                'identifier'=>'bulksmsnigeria',
                'display_name'=>'BulkSMSNigeria',
                'base_url'=>'https://www.bulksmsnigeria.com/api/v2',
                'documentation_url'=>'https://www.bulksmsnigeria.com/api-documentation',
                'official_website'=>'https://www.bulksmsnigeria.com/',
                'auth_type'=>'custom',
                'service_categories'=>['messaging'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'sms','provider_service_id'=>'sms','capabilities'=>[]],
                    ['service_key'=>'otp','provider_service_id'=>'otp-message-delivery','capabilities'=>[]],
                ],
                'priority'=>80,
            ],
            [
                'identifier'=>'infobip',
                'display_name'=>'Infobip',
                'base_url'=>'https://api.infobip.com',
                'documentation_url'=>'https://www.infobip.com/docs/sms',
                'official_website'=>'https://www.infobip.com/',
                'auth_type'=>'custom',
                'service_categories'=>['messaging'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'sms','provider_service_id'=>'sms','capabilities'=>[]],
                    ['service_key'=>'otp','provider_service_id'=>'2fa','capabilities'=>[]],
                ],
                'priority'=>81,
            ],
            [
                'identifier'=>'twilio-verify',
                'display_name'=>'Twilio Verify',
                'base_url'=>'https://verify.twilio.com/v2',
                'documentation_url'=>'https://www.twilio.com/docs/verify/api',
                'official_website'=>'https://www.twilio.com/',
                'auth_type'=>'custom',
                'service_categories'=>['messaging'],
                'capabilities'=>[],
                'endpoints'=>[],
                'api_version'=>null,
                'mappings'=>[
                    ['service_key'=>'otp','provider_service_id'=>'verify','capabilities'=>[]],
                ],
                'priority'=>82,
            ],
        ];
    }
}
