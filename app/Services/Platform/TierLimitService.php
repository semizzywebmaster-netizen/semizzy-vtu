<?php

namespace App\Services\Platform;

use App\Models\SystemSetting;

class TierLimitService
{
    public function all(): array
    {
        $keys=collect(range(1,4))->flatMap(fn($tier)=>['tier_'.$tier.'_daily_limit_minor','tier_'.$tier.'_balance_limit_minor'])->all();
        $stored=SystemSetting::query()->whereIn('key',$keys)->pluck('value','key');
        return collect(range(1,4))->mapWithKeys(function($tier) use($stored){$base=config("semizzy.user_tiers.$tier",[]);return [$tier=>[
            'name'=>$base['name']??'Tier '.$tier,'daily_limit_minor'=>$stored->get('tier_'.$tier.'_daily_limit_minor',$base['daily_limit_minor']),'balance_limit_minor'=>$stored->get('tier_'.$tier.'_balance_limit_minor',$base['balance_limit_minor']),
            'upgrade_label'=>$base['upgrade_label']??null,'type'=>$base['type']??'personal','requirements'=>$base['requirements']??[],
        ]];})->all();
    }
    public function get(int $tier): array { return $this->all()[max(1,min(4,$tier))]; }
}
