<?php

namespace App\Console\Commands;

use Semizzy\Addons\Payments\Models\PaymentIntent;
use Illuminate\Console\Command;

class PaymentsExpirePending extends Command
{
    protected $signature = 'payments:expire-pending {--limit=100}';
    protected $description = 'Mark expired pending payment intents as expired without touching wallet balances.';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $count = PaymentIntent::query()
            ->whereIn('status', ['pending', 'processing'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->limit($limit)
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $this->info("Expired {$count} pending payment intent(s).");
        return self::SUCCESS;
    }
}
