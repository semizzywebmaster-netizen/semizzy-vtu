<?php

namespace App\Services\Communication;

use App\Jobs\SendCommunicationProviderDelivery;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationDeliveryLog;
use App\Models\User;
use App\Notifications\CoreNotification;

class CommunicationCenterService
{
    private const SUPPORTED_CHANNELS = ['web_push', 'email', 'sms', 'whatsapp'];

    public function recipients(array $targets)
    {
        $q = User::query()->where('status', 'active');

        foreach ($targets as $key => $value) {
            if ($key === 'all' && $value) {
                $q = User::query()->where('status', 'active');
            } elseif ($key === 'tiers' && is_array($value)) {
                $q->whereIn('tier', array_map('intval', $value));
            } elseif ($key === 'roles' && is_array($value)) {
                $q->whereIn('role', $value);
            } elseif ($key === 'verified' && $value === true) {
                $q->whereNotNull('email_verified_at');
            } elseif ($key === 'verified' && $value === false) {
                $q->whereNull('email_verified_at');
            } elseif ($key === 'new_days' && (int) $value > 0) {
                $q->where('created_at', '>=', now()->subDays((int) $value));
            } elseif ($key === 'inactive_days' && (int) $value > 0) {
                $q->where(function ($inner) use ($value) {
                    $inner->whereNull('last_login_at')
                        ->orWhere('last_login_at', '<', now()->subDays((int) $value));
                });
            } elseif ($key === 'user_ids' && is_array($value)) {
                $q->whereIn('id', array_map('intval', $value));
            }
        }

        return $q;
    }

    public function send(CommunicationCampaign $campaign): int
    {
        $requested = array_values(array_unique($campaign->channels ?? []));
        $supported = array_values(array_intersect($requested, self::SUPPORTED_CHANNELS));
        $unsupported = array_values(array_diff($requested, self::SUPPORTED_CHANNELS));

        if ($supported === []) {
            $campaign->forceFill([
                'status' => 'blocked',
                'result_summary' => [
                    'queued' => 0,
                    'requested_channels' => $requested,
                    'unsupported_channels' => $unsupported,
                    'reason' => 'No configured delivery channel is supported.',
                ],
            ])->save();

            return 0;
        }

        $queued = 0;

        $this->recipients($campaign->targets ?? [])
            ->select(['id', 'email', 'phone'])
            ->orderBy('id')
            ->chunkById(250, function ($users) use ($campaign, $supported, $unsupported, &$queued): void {
                foreach ($users as $user) {
                    if (in_array('web_push', $supported, true)) {
                        CommunicationDeliveryLog::create([
                            'campaign_id' => $campaign->id,
                            'user_id' => $user->id,
                            'channel' => 'web_push',
                            'status' => 'queued',
                            'attempts' => 0,
                            'queued_at' => now(),
                        ]);
                    }

                    if (in_array('email', $supported, true)) {
                        CommunicationDeliveryLog::create([
                            'campaign_id' => $campaign->id,
                            'user_id' => $user->id,
                            'channel' => 'email',
                            'status' => filled($user->email) ? 'queued' : 'skipped',
                            'attempts' => 0,
                            'queued_at' => filled($user->email) ? now() : null,
                            'error_message' => filled($user->email) ? null : 'Recipient has no email address configured.',
                        ]);
                    }

                    foreach (['sms', 'whatsapp'] as $channel) {
                        if (!in_array($channel, $supported, true)) {
                            continue;
                        }

                        if (!filled($user->phone)) {
                            CommunicationDeliveryLog::create([
                                'campaign_id' => $campaign->id,
                                'user_id' => $user->id,
                                'channel' => $channel,
                                'status' => 'skipped',
                                'attempts' => 0,
                                'error_message' => 'Recipient has no phone number configured.',
                            ]);
                            continue;
                        }

                        $log = CommunicationDeliveryLog::create([
                            'campaign_id' => $campaign->id,
                            'user_id' => $user->id,
                            'channel' => $channel,
                            'status' => 'queued',
                            'attempts' => 0,
                            'queued_at' => now(),
                        ]);

                        SendCommunicationProviderDelivery::dispatch($log->id);
                    }

                    if ($supported !== []) {
                        $notificationChannels = array_values(array_intersect($supported, ['web_push', 'email']));
                        if ($notificationChannels !== []) {
                            $user->notify(new CoreNotification(
                                $campaign->title,
                                $campaign->message,
                                $campaign->url,
                                $notificationChannels
                            ));
                        }
                    }

                    $queued++;
                }
            });

        $campaign->forceFill([
            'status' => $unsupported === [] ? 'queued' : 'partial',
            'sent_at' => now(),
            'result_summary' => [
                'queued' => $queued,
                'requested_channels' => $requested,
                'queued_channels' => $supported,
                'unsupported_channels' => $unsupported,
                'delivery_note' => 'Queued means accepted by the application queue. Final delivery is recorded per recipient/channel in communication delivery logs.',
            ],
        ])->save();

        return $queued;
    }
}
