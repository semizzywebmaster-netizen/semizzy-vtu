<?php
namespace Addons\InsuranceProtection\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceProvider extends Model { protected $table='insurance_providers'; protected $guarded=[]; protected $casts=['credentials'=>'encrypted:array','capabilities'=>'array','enabled'=>'boolean','paused'=>'boolean','cooldown_until'=>'datetime','last_success_at'=>'datetime','last_failure_at'=>'datetime']; }