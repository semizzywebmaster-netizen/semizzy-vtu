<?php
namespace Semizzy\Addons\P2p\Services;

use App\Models\Addon;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Brick\Math\BigInteger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\P2p\Models\P2pTransfer;

final class P2pTransferService
{
    private function sub(string $a, string $b): string { return BigInteger::of($a)->minus($b)->__toString(); }
    private function add(string $a, string $b): string { return BigInteger::of($a)->plus($b)->__toString(); }
    private function gte(string $a, string $b): bool { return BigInteger::of($a)->compareTo($b) >= 0; }

    public function transfer(int $senderId, string $recipientQuery, string $amountMinor, ?string $note, string $idempotencyKey): P2pTransfer
    {
        if (!preg_match('/^\d+$/', $amountMinor) || BigInteger::of($amountMinor)->isZero() || BigInteger::of($amountMinor)->isNegative()) {
            throw new RuntimeException('Transfer amount must be a positive integer minor-unit value.');
        }

        $settings = Addon::query()->where('identifier', 'p2p.transfers')->value('settings_schema') ?? [];
        $defaults = collect(is_array($settings) ? $settings : [])->mapWithKeys(function ($item) {
            return [($item['key'] ?? '') => $item['default'] ?? null];
        });
        $currency = strtoupper((string) ($defaults->get('default_currency') ?: 'NGN'));
        $maxTransfer = (string) ($defaults->get('max_transfer_minor') ?? '1000000000');
        $fee = (string) ($defaults->get('fee_minor') ?? '0');

        if ($currency !== 'NGN') {
            throw new RuntimeException('P2P currently supports NGN wallet transfers only.');
        }
        if (!preg_match('/^\d+$/', $maxTransfer) || !$this->gte($maxTransfer, $amountMinor)) {
            throw new RuntimeException('Transfer amount exceeds the configured P2P transfer limit.');
        }
        if (!preg_match('/^\d+$/', $fee) || BigInteger::of($fee)->isNegative()) {
            throw new RuntimeException('P2P fee must be a non-negative integer minor-unit value.');
        }
        if (BigInteger::of($fee)->isPositive()) {
            throw new RuntimeException('A non-zero P2P fee is configured, but no platform fee wallet is configured. Set the P2P fee to ₦0 until fee settlement is enabled.');
        }

        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new RuntimeException('A valid idempotency key is required.');
        }

        try {
            return DB::transaction(function () use ($senderId, $recipientQuery, $amountMinor, $note, $idempotencyKey, $currency, $fee) {
                $recipientQuery = trim($recipientQuery);
                if ($recipientQuery === '') {
                    throw new RuntimeException('A recipient username, email, or phone number is required.');
                }

                $matches = User::query()
                    ->where(function ($q) use ($recipientQuery) {
                        $q->where('username', $recipientQuery)
                          ->orWhere('email', $recipientQuery)
                          ->orWhere('phone', $recipientQuery);
                    })
                    ->limit(2)
                    ->get(['id']);

                if ($matches->count() !== 1) {
                    throw new RuntimeException($matches->isEmpty()
                        ? 'Recipient account not found.'
                        : 'Recipient identifier is ambiguous. Use the recipient username.');
                }

                $recipient = $matches->first();

                $existing = P2pTransfer::where('sender_id', $senderId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()->first();

                if ($existing) {
                    if ((string) $existing->amount_minor !== $amountMinor || (int) $existing->recipient_id !== (int) $recipient->id) {
                        throw new RuntimeException('This idempotency key has already been used for a different transfer.');
                    }
                    return $existing;
                }
                if ($recipient->id === $senderId) {
                    throw new RuntimeException('You cannot transfer to yourself.');
                }

                $users = [$senderId, $recipient->id];
                sort($users, SORT_NUMERIC);

                $wallets = WalletAccount::whereIn('user_id', $users)
                    ->where('currency', 'NGN')
                    ->lockForUpdate()->get()->keyBy('user_id');

                $sender = $wallets->get($senderId);
                $receiver = $wallets->get($recipient->id);
                if (!$sender || $sender->status !== 'active' || !$receiver || $receiver->status !== 'active') {
                    throw new RuntimeException('Both sender and recipient must have active NGN wallets.');
                }

                $totalDebit = $this->add($amountMinor, $fee);
                if (!$this->gte((string) $sender->available_minor, $totalDebit)) {
                    throw new RuntimeException('Insufficient wallet balance.');
                }

                $beforeSender = (string) $sender->available_minor;
                $beforeReceiver = (string) $receiver->available_minor;
                $sender->available_minor = $this->sub($beforeSender, $totalDebit);
                $receiver->available_minor = $this->add($beforeReceiver, $amountMinor);
                $sender->save();
                $receiver->save();

                $reference = 'P2P-' . strtoupper(Str::random(20));
                $tx = P2pTransfer::create([
                    'sender_id' => $senderId, 'recipient_id' => $recipient->id,
                    'reference' => $reference, 'idempotency_key' => $idempotencyKey,
                    'amount_minor' => $amountMinor, 'fee_minor' => $fee,
                    'currency' => $currency, 'status' => 'completed', 'note' => $note,
                    'metadata' => ['addon' => 'p2p.transfers'],
                ]);

                WalletMovement::create([
                    'wallet_account_id' => $sender->id, 'operation_key' => 'p2p:debit:' . $tx->id,
                    'reference' => $reference, 'type' => 'debit', 'amount_minor' => $totalDebit,
                    'currency' => $currency, 'available_before_minor' => $beforeSender,
                    'available_after_minor' => $sender->available_minor,
                    'held_before_minor' => $sender->held_minor, 'held_after_minor' => $sender->held_minor,
                    'metadata' => ['addon' => 'p2p.transfers', 'transfer_id' => $tx->id, 'recipient_id' => $recipient->id],
                ]);
                WalletMovement::create([
                    'wallet_account_id' => $receiver->id, 'operation_key' => 'p2p:credit:' . $tx->id,
                    'reference' => $reference, 'type' => 'credit', 'amount_minor' => $amountMinor,
                    'currency' => $currency, 'available_before_minor' => $beforeReceiver,
                    'available_after_minor' => $receiver->available_minor,
                    'held_before_minor' => $receiver->held_minor, 'held_after_minor' => $receiver->held_minor,
                    'metadata' => ['addon' => 'p2p.transfers', 'transfer_id' => $tx->id, 'sender_id' => $senderId],
                ]);

                return $tx->fresh();
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') || str_contains(strtolower($e->getMessage()), 'duplicate')) {
                $existing = P2pTransfer::where('sender_id', $senderId)->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ((string) $existing->amount_minor !== $amountMinor || (int) $existing->recipient_id !== (int) User::query()->where(function ($q) use ($recipientQuery) {
                        $q->where('username', trim($recipientQuery))
                          ->orWhere('email', trim($recipientQuery))
                          ->orWhere('phone', trim($recipientQuery));
                    })->value('id')) {
                        throw new RuntimeException('This idempotency key has already been used for a different transfer.');
                    }
                    return $existing;
                }
            }
            throw $e;
        }
    }
}
