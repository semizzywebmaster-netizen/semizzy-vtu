<?php

namespace Addons\BankingFinancialIntegrations\Services;

use Addons\BankingFinancialIntegrations\Models\BankDirectory;
use Addons\BankingFinancialIntegrations\Models\AccountVerification;
use RuntimeException;

class BankNameEnquiryService
{
    public function createRequest(int $userId, int $bankId, string $accountNumber): AccountVerification
    {
        $bank = BankDirectory::query()->whereKey($bankId)->where('active', true)->first();
        if (!$bank) {
            throw new RuntimeException('Selected financial institution is not available.');
        }

        $accountNumber = preg_replace('/\D+/', '', $accountNumber) ?? '';
        if (strlen($accountNumber) !== 10) {
            throw new RuntimeException('Nigerian account number must contain 10 digits.');
        }

        return AccountVerification::create([
            'user_id' => $userId,
            'bank_directory_id' => $bank->id,
            'account_number' => $accountNumber,
            'request_reference' => 'BAV-' . strtoupper(bin2hex(random_bytes(12))),
            'status' => 'pending',
        ]);
    }
}
