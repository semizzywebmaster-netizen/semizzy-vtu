<?php
namespace Addons\WhatsAppBot\Observers;

use Addons\WhatsAppBot\Services\WhatsAppTransactionNotificationService;
use App\Models\VtuTransaction;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class VtuTransactionObserver implements ShouldHandleEventsAfterCommit
{
 public function updated(VtuTransaction $transaction): void
 {
  if(!$transaction->wasChanged('status'))return;
  app(WhatsAppTransactionNotificationService::class)->notify($transaction,(string)$transaction->status);
 }
}
