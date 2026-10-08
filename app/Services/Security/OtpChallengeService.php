<?php

namespace App\Services\Security;

use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

class OtpChallengeService
{
    public function send(User $user, string $purpose, string $label): void
    {
        $this->sendToUser($user, $purpose, $label);
    }

    public function sendToUser(User $user, string $purpose, string $label): void
    {
        $purpose = $this->normalizePurpose($purpose);

        if (! filled($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'A valid email address is required.']);
        }

        OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpChallenge::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'purpose' => $purpose,
            'destination' => (string) $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'attempts' => 0,
            'max_attempts' => 5,
            'ip_address' => request()->ip(),
        ]);

        Mail::raw(
            "Your SEMIZZY ONE {$label} verification code is {$code}. It expires in 10 minutes. If you did not request this, secure your account immediately.",
            fn ($message) => $message->to($user->email)->subject("SEMIZZY ONE {$label} verification")
        );
    }

    public function sendRegistration(User $user, string $channel, int $expiryMinutes = 10, int $maxAttempts = 5): void
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, ['email','sms','whatsapp'], true)) throw new \InvalidArgumentException('Unsupported OTP channel.');
        if ($channel === 'email') {
            if (! filled($user->email) || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['email'=>'A valid email address is required.']);
            $destination = (string) $user->email;
        } else {
            if (! filled($user->phone)) throw ValidationException::withMessages(['phone'=>'A valid phone number is required for this verification channel.']);
            if (! Schema::hasTable('communication_providers')) throw ValidationException::withMessages(['verification_channel'=>'This verification channel is not configured yet.']);
            $destination = (string) $user->phone;
            $provider = \App\Models\Communication\Provider::query()->where('channel',$channel)->where('enabled',true)->where('paused',false)->exists();
            if (! $provider) throw ValidationException::withMessages(['verification_channel'=>'No active provider is configured for this verification channel.']);
        }
        OtpChallenge::query()->where('user_id',$user->id)->where('purpose','registration')->whereNull('consumed_at')->update(['consumed_at'=>now()]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpChallenge::create(['user_id'=>$user->id,'channel'=>$channel,'purpose'=>'registration','destination'=>$destination,'code_hash'=>Hash::make($code),'expires_at'=>now()->addMinutes($expiryMinutes),'consumed_at'=>null,'attempts'=>0,'max_attempts'=>$maxAttempts,'ip_address'=>request()->ip()]);
        $body = "Your registration verification code is {$code}. It expires in {$expiryMinutes} minutes.";
        if ($channel === 'email') {
            Mail::raw($body, fn($message) => $message->to($destination)->subject('Registration verification code'));
            return;
        }
        $conversation = \App\Models\Communication\Conversation::firstOrCreate(['channel'=>$channel,'external_contact'=>$destination],['user_id'=>$user->id,'status'=>'open']);
        $message = \App\Models\Communication\Message::create(['conversation_id'=>$conversation->id,'user_id'=>$user->id,'channel'=>$channel,'direction'=>'outbound','recipient'=>$destination,'body'=>$body,'status'=>'queued','idempotency_key'=>'registration-otp-'.$user->id.'-'.bin2hex(random_bytes(8)),'metadata'=>['purpose'=>'registration']]);
        app(\Addons\CommunicationWhatsapp\Services\CommunicationProviderGateway::class)->send($message);
    }

    public function verify(User $user, string $purpose, string $code): void
    {
        $this->verifyForUser($user, $purpose, $code);
    }

    public function verifyForUser(User $user, string $purpose, string $code): void
    {
        $purpose = $this->normalizePurpose($purpose);
        $challenge = OtpChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $challenge || $challenge->expires_at?->isPast() || $challenge->attempts >= $challenge->max_attempts) {
            throw ValidationException::withMessages(['otp_code' => 'Your verification code is invalid or expired. Request a new code.']);
        }

        if (! Hash::check($code, (string) $challenge->code_hash)) {
            $challenge->increment('attempts');
            throw ValidationException::withMessages(['otp_code' => 'The verification code is incorrect.']);
        }

        $challenge->forceFill(['consumed_at' => now()])->saveOrFail();
    }

    private function normalizePurpose(string $purpose): string
    {
        return match ($purpose) {
            'transaction_pin_change', 'password_change', 'password_forgot', 'new_device_login', 'registration' => $purpose,
            default => throw new \InvalidArgumentException('Unsupported OTP purpose.'),
        };
    }
}