<?php
namespace Semizzy\Addons\Smm\Models;
use Illuminate\Database\Eloquent\Model;
final class SmmServiceCategory extends Model {protected $table='smm_service_categories'; protected $guarded=[]; protected function casts():array{return ['active'=>'boolean','metadata'=>'array'];}}