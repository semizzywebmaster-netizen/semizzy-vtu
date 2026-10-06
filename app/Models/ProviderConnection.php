<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;
use Illuminate\\Database\\Eloquent\\SoftDeletes;

class ProviderConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'api_provider_id','name','environment','base_url','api_version','api_prefix',
        'auth_type','auth_options','connect_timeout_seconds','request_timeout_seconds',
        'verify_ssl','headers','query_params','proxy','is_default','enabled',
        'last_tested_at','last_test_status','last_test_message',
    ];

    protected $hidden = ['auth_options'];

    protected function casts(): array
    {
        return [
            'auth_options'=>'array','headers'=>'array','query_params'=>'array','proxy'=>'array',
            'verify_ssl'=>'boolean','is_default'=>'boolean','enabled'=>'boolean',
            'last_tested_at'=>'datetime',
        ];
    }

    public function provider(): BelongsTo { return $this->belongsTo(ApiProvider::class, 'api_provider_id'); }
    public function credentials(): HasMany { return $this->hasMany(ProviderCredential::class); }
}
