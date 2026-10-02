<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiProvider extends Model
{
    use SoftDeletes;

    protected $fillable = ['identifier','display_name','official_website','documentation_url','service_categories','capabilities','endpoints','api_version','auth_type','environment','base_url','credentials','verification_status','integration_status','enabled','paused','priority','timeout_seconds','notes','last_tested_at','last_test_status','last_test_summary','last_successful_request_at'];

    protected function casts(): array { return [
        'service_categories'=>'array','capabilities'=>'array','endpoints'=>'array','credentials'=>'encrypted:array','notes'=>'array',
        'enabled'=>'boolean','paused'=>'boolean','last_tested_at'=>'datetime','last_successful_request_at'=>'datetime',
    ];}

    public function serviceMappings(): HasMany { return $this->hasMany(ProviderServiceMapping::class); }

    public function scopeEligibleForNewTransactions(Builder $query): Builder
    {
        return $query->where('enabled',true)->where('paused',false)->where('integration_status','live_verified')->where('verification_status','live_verified');
    }

    public function maskedCredentials(): array
    {
        return collect($this->credentials ?? [])->mapWithKeys(fn($value,$key)=>[$key=>$value===null||$value===''?null:'••••••••'])->all();
    }
}
