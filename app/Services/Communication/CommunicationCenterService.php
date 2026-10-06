<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use App\Models\User;
use App\Notifications\CoreNotification;
use Illuminate\Support\Facades\DB;

class CommunicationCenterService
{
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
        $supported = array_values(array_intersect($requested, ['web_push', 'email']));
        $unsupported = array_values(array_diff($requested, ['web_push', 'email']));

        if ($supported === []) {
            $campaign->forceFill([
                'status' => 'blocked',
                'result_summary' => [
                    'queued' => 0,
                    'requested_channels' => $requested,
                    'unsupported_channels' => $unsupported,
                    'reason' => 'No configured delivery channel can deliver this campaign yet.',
                ],
            ])->save();

            return 0;
        }

        $queued = 0;

        $this->recipients($campaign->targets ?? [])
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(250, function ($users) use ($campaign, $supported, &$queued): void {
                foreach ($users as $user) {
                    $user->notify(new CoreNotification(
                        $campaign->title,
                        $campaign->message,
                        $campaign->url,
                        $supported
                    ));
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
                'delivery_note' => 'Queued notifications are not counted as delivered until the queue worker processes them.',
            ],
        ])->save();

        return $queued;
    }
}
