<?php
namespace App\Console\Commands;

use App\Models\CacOrder;
use App\Services\Cac\CacProviderGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CacReconcilePending extends Command
{
    protected $signature = 'cac:reconcile-pending {--limit=50} {--min-age=1}';
    protected $description = 'Safely requery pending CAC provider orders.';

    public function handle(CacProviderGateway $gateway): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $minAge = max(0, min(1440, (int) $this->option('min-age')));
        $items = CacOrder::query()
            ->whereIn('status', ['pending_requery','processing'])
            ->whereNotNull('provider_reference')
            ->whereNotNull('api_provider_id')
            ->where('updated_at', '<=', now()->subMinutes($minAge))
            ->oldest('updated_at')->oldest('id')->limit($limit)->get();

        foreach ($items as $order) {
            try {
                $result = $gateway->requery($order->fresh(['product','provider']));
                DB::transaction(function () use ($order, $result) {
                    $locked = CacOrder::query()->lockForUpdate()->findOrFail($order->id);
                    $n = (int) $locked->attempts()->max('attempt_number') + 1;
                    $locked->attempts()->create([
                        'api_provider_id'=>$result->providerId ?: $locked->api_provider_id,
                        'attempt_number'=>$n,
                        'operation'=>'transaction_status',
                        'status'=>$result->status,
                        'provider_reference'=>$result->providerReference ?: $locked->provider_reference,
                        'request_payload'=>['reference'=>$locked->provider_reference,'transaction_reference'=>$locked->reference],
                        'response_payload'=>is_array($result->data) ? $result->data : null,
                        'error_message'=>$result->message,
                    ]);
                    if ($result->providerReference) $locked->provider_reference=$result->providerReference;
                    $locked->response_payload=is_array($result->data) ? $result->data : $locked->response_payload;
                    if ($result->accepted) {
                        $locked->status='completed'; $locked->completed_at=now(); $locked->failure_message=null;
                    } elseif (strtoupper($result->status)==='FAILED') {
                        $locked->status='failed'; $locked->completed_at=now(); $locked->failure_message=$result->message ?: 'Provider reports failure.';
                    } else {
                        $locked->status='pending_requery'; $locked->failure_message=$result->message;
                    }
                    $locked->save();
                });
            } catch (\Throwable $e) {
                report($e);
                $this->warn($order->reference.' remains pending; reconciliation failed safely.');
            }
        }
        $this->info('CAC reconciliation complete: '.$items->count().' order(s) checked.');
        return self::SUCCESS;
    }
}
