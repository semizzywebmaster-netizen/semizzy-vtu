<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class OperationalRunbooksController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/OperationalRunbooks', [
            'runbooks' => [
                [
                    'id' => 'provider-outage',
                    'title' => 'Provider outage or high error rate',
                    'severity' => 'High',
                    'steps' => [
                        'Open API Providers and review the provider health, last successful test, recent failures and sync history.',
                        'Run a connection/operation test only when safe; do not repeatedly submit live purchase requests as a health check.',
                        'Pause the affected provider if evidence indicates unsafe fulfilment. Confirm a verified fallback route exists before relying on it.',
                        'Requery pending transactions by their original reference. Do not create replacement purchases until the original status is known.',
                        'Record the incident, provider response references, time window and recovery action in the audit trail.',
                    ],
                ],
                [
                    'id' => 'pending-transaction',
                    'title' => 'Transaction stuck in pending',
                    'severity' => 'High',
                    'steps' => [
                        'Search for the original transaction/reference and confirm the wallet movement and idempotency key.',
                        'Use the supported provider status/requery operation and retain the provider response reference.',
                        'Do not retry purchase initiation blindly; a timeout does not prove the provider did not fulfil the request.',
                        'Complete, fail or refund only according to verified provider status and the approved transaction state machine.',
                        'Escalate unresolved cases with redacted request IDs and timestamps; never include credentials or OTPs.',
                    ],
                ],
                [
                    'id' => 'refund-review',
                    'title' => 'Refund or reversal investigation',
                    'severity' => 'High',
                    'steps' => [
                        'Confirm the original debit, transaction state and customer report before acting.',
                        'Check provider-confirmed reversal/refund status and reference where the provider supports it.',
                        'Ensure the refund operation is idempotent and the wallet ledger cannot be credited twice.',
                        'Require the configured authorization/approval path for manual adjustments and record the reason.',
                        'Reconcile the provider amount, platform ledger amount and final customer balance before closing the case.',
                    ],
                ],
                [
                    'id' => 'catalogue-price-change',
                    'title' => 'Catalogue sync or source-price change',
                    'severity' => 'Medium',
                    'steps' => [
                        'Review the sync summary and confirm the fetch completed successfully, including pagination where supported.',
                        'Compare provider source cost, currency and external product ID with the prior stored values.',
                        'Do not treat an item absent from an incomplete response as permanently removed.',
                        'Verify tier prices through the platform pricing engine and review margin policy before publishing.',
                        'Keep source-cost sync separate from customer selling prices, visibility and provider routing.',
                    ],
                ],
                [
                    'id' => 'email-notifications',
                    'title' => 'Email or notification delivery failure',
                    'severity' => 'Medium',
                    'steps' => [
                        'Open Email & SMTP settings and test the intended profile using an authorized test recipient.',
                        'Check sender verification, DNS, host, port, encryption, credentials and provider limits.',
                        'Review configured failover profiles and confirm that sensitive error details are not exposed to customers.',
                        'Retry only through the platform delivery workflow and check for duplicate notifications.',
                        'Record the affected channel and time window; never paste SMTP passwords into tickets or audit notes.',
                    ],
                ],
                [
                    'id' => 'backup-restore',
                    'title' => 'Backup, restore or deployment recovery',
                    'severity' => 'Critical',
                    'steps' => [
                        'Before restoring, confirm the backup provenance, creation time and whether database/files are a consistent pair.',
                        'Preserve the current state before restore where possible and restrict the operation to authorized administrators.',
                        'Restore into a safe test/staging environment first whenever available; do not experiment on live financial data.',
                        'After restore, verify login, database migrations, storage permissions, provider secret decryption, queues/cron and key user journeys.',
                        'Run reconciliation checks before reopening financial operations and document the result.',
                    ],
                ],
            ],
        ]);
    }
}
