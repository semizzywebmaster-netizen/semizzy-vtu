<?php

namespace Semizzy\Addons\Kyc\Events;

class KycOtpRequested
{
    public function __construct(
        public readonly int $userId,
        public readonly string $channel,
        public readonly string $destination,
        public readonly string $code,
        public readonly int $verificationId,
    ) {}
}
