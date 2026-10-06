<?php

namespace Semizzy\Addons\Kyc\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Semizzy\Addons\Kyc\Models\KycApplication;

class KycOtpService
{
    public function issue(int $userId, string $channel, string $destination, ?KycApplication $application = null): array
    {
        $channel = strtolower(trim($channel));
        if (!in_array($channel, ['sms', 'email', 'whatsapp'], true)) {
            throw new RuntimeException('Unsupported verification channel.');
        }

        $code = (string) random_int(100000, 999999);
        $destinationHash = hash('sha256', Str::lower(trim($destination)));

        DB::table('kyc_verifications')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->update(['status' => 'superseded', 'updated_at' => now()]);

        $id = DB::table('kyc_verifications')->insertGetId([
            'user_id' => $userId,
            'kyc_application_id' => $application?->id,
            'channel' => $channel,
            'destination_hash' => $destinationHash,
            'code_hash' => hash('sha256', $code),
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(10),
            'last_sent_at' => now(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        event(new \App\Events\KycOtpRequested(
            $userId,
            $channel,
            $destination,
            $code,
            $id
        ));

        return ['id' => $id, 'expires_at' => now()->addMinutes(10)->toISOString()];
    }

    public function verify(int $userId, string $code): bool
    {
        $record = DB::table('kyc_verifications')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->latest('id')->first();

        if (!$record || now()->greaterThan($record->expires_at) || $record->attempts >= $record->max_attempts) {
            return false;
        }

        $matched = hash_equals($record->code_hash, hash('sha256', trim($code)));
        DB::table('kyc_verifications')->where('id', $record->id)->update([
            'attempts' => $record->attempts + 1,
            'status' => $matched ? 'verified' : 'pending',
            'verified_at' => $matched ? now() : null,
            'updated_at' => now(),
        ]);

        return $matched;
    }
}
