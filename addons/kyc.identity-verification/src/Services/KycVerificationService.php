<?php

namespace Semizzy\Addons\Kyc\Services;

use App\Services\Providers\ProviderManager;
use App\Services\Providers\ProviderResult;
use Semizzy\Addons\Kyc\Models\KycApplication;
use RuntimeException;

final class KycVerificationService
{
    public const SERVICE_KEY = 'kyc.identity-verification';
    public const OPERATION = 'kyc_verification';

    public function __construct(private ProviderManager $providers) {}

    public function verify(KycApplication $application): ?ProviderResult
    {
        try {
            $result = $this->providers->execute(
                self::SERVICE_KEY,
                self::OPERATION,
                [
                    'identity_type' => $application->identity_type,
                    'identity_number' => $application->identity_number,
                    'application_id' => $application->id,
                ],
                'kyc-'.$application->id
            );
        } catch (RuntimeException) {
            return null;
        }

        $application->forceFill([
            'provider_id' => $result->providerId,
            'provider_reference' => $result->providerReference,
            'verification_status' => strtoupper($result->status),
            'verification_checked_at' => now(),
        ])->save();

        return $result;
    }
}
