<?php

namespace Semizzy\Addons\Savings\Services;

use Semizzy\Addons\Savings\Models\SavingsAccount;

class SavingsMaturityService
{
    public function process(int $limit = 100): int
    {
        $count = 0;
        SavingsAccount::where('status', 'active')->whereNotNull('matures_at')->where('matures_at', '<=', now())->orderBy('id')->limit($limit)->get()->each(function (SavingsAccount $account) use (&$count): void { $account->status = 'matured'; $account->saveQuietly(); $count++; });
        return $count;
    }
}
