<?php

namespace App\Services\Dashboard;

use App\Models\DashboardMessage;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardMessageService
{
    public function compose(User $user): array
    {
        $period = $this->period((int) now()->hour);
        $audience = $user->isMerchant() ? 'merchant' : ($user->role === 'AGENT' ? 'agent' : null);
        $tier = max(1, min(4, (int) $user->tier));

        $base = DashboardMessage::query()->active()->whereIn('type', ['greeting','quote'])->get();
        $greetings = $base->filter(fn (DashboardMessage $m) =>
            $m->type === 'greeting' &&
            (!$m->time_period || $m->time_period === $period) &&
            $this->matchesTier($m, $tier) &&
            $this->matchesAudience($m, $audience)
        );
        $quotes = $base->filter(fn (DashboardMessage $m) =>
            $m->type === 'quote' &&
            $this->matchesTier($m, $tier) &&
            $this->matchesAudience($m, $audience)
        );

        $seasonal = DashboardMessage::query()->active()->where('type', 'seasonal')->get()
            ->filter(fn (DashboardMessage $m) => $this->matchesTier($m, $tier) && $this->matchesAudience($m, $audience));
        $promotional = DashboardMessage::query()->active()->where('type', 'promotional')->get()
            ->filter(fn (DashboardMessage $m) => $this->matchesTier($m, $tier) && $this->matchesAudience($m, $audience));

        return [
            'greeting' => $this->pick($greetings, $user->id),
            'quote' => $this->pick($quotes, $user->id),
            'seasonal' => $this->pick($seasonal, $user->id),
            'promotional' => $this->pick($promotional, $user->id),
            'period' => $period,
            'tier' => $tier,
            'audience' => $audience,
        ];
    }

    private function pick(Collection $messages, int $userId): ?array
    {
        if ($messages->isEmpty()) return null;
        $ordered = $messages->sortByDesc('priority')->values();
        $item = $ordered[$userId % $ordered->count()];
        return ['id'=>$item->id, 'title'=>$item->title, 'message'=>$this->render($item->message, $userId)];
    }

    private function render(string $message, int $userId): string
    {
        $user = User::query()->select(['id','name','business_name'])->find($userId);
        $name = $user?->isMerchant() ? ($user->business_name ?: $user->name) : ($user?->name ?: 'there');
        return str_replace([':name','{name}'], $name, $message);
    }

    private function matchesTier(DashboardMessage $m, int $tier): bool
    {
        return empty($m->tiers) || in_array($tier, array_map('intval', $m->tiers), true);
    }

    private function matchesAudience(DashboardMessage $m, ?string $audience): bool
    {
        if (empty($m->audiences)) return true;
        return $audience !== null && in_array($audience, $m->audiences, true);
    }

    private function period(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 17 => 'afternoon',
            $hour >= 17 && $hour < 22 => 'evening',
            default => 'night',
        };
    }
}