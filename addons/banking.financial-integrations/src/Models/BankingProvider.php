<?php
namespace Addons\BankingFinancialIntegrations\Models;

use Illuminate\Database\Eloquent\Model;

class BankingProvider extends Model
{
 protected $table = 'banking_providers';
 protected $guarded = [];
 protected $casts = [
  'credentials' => 'encrypted:array',
  'capabilities' => 'array',
  'enabled' => 'boolean',
  'paused' => 'boolean',
  'maintenance' => 'boolean',
  'cooldown_until' => 'datetime',
  'last_success_at' => 'datetime',
  'last_failure_at' => 'datetime',
  'last_health_check_at' => 'datetime',
 ];
}