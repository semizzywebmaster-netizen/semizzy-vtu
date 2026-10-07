<?php

namespace App\Services\Vtu;

use App\Services\Providers\ProviderManager;

class VtuNetworkResolver
{
    public function __construct(private ProviderManager $providers) {}

    public function resolve(string $phone): ?string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($normalized, '234')) {
            $normalized = '0' . substr($normalized, 3);
        }

        if (!preg_match('/^0\d{10}$/', $normalized)) {
            return null;
        }

        foreach ($this->providers->eligible('airtime', 'network_lookup') as $provider) {
            $result = $this->providers->executeProvider(
                $provider,
                'airtime',
                'network_lookup',
                ['phone' => $normalized],
            );

            if (!$result->accepted || !is_array($result->data)) {
                continue;
            }

            $network = $this->extractNetwork($result->data);
            if ($network !== null) {
                return $network;
            }
        }

        return null;
    }

    private function extractNetwork(array $data): ?string
    {
        $candidates = [
            $data['network'] ?? null,
            $data['operator'] ?? null,
            $data['mno'] ?? null,
            $data['data']['network'] ?? null,
            $data['data']['operator'] ?? null,
            $data['data']['mno'] ?? null,
            $data['result']['network'] ?? null,
            $data['result']['operator'] ?? null,
            $data['result']['mno'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (!is_string($value)) {
                continue;
            }

            $key = strtolower(trim($value));
            $map = [
                'mtn' => 'mtn',
                'airtel' => 'airtel',
                'glo' => 'glo',
                'globacom' => 'glo',
                '9mobile' => '9mobile',
                'etisalat' => '9mobile',
                'lebara' => 'lebara',
                'lebara nigeria' => 'lebara',
            ];

            if (isset($map[$key])) {
                return $map[$key];
            }
        }

        return null;
    }
}
