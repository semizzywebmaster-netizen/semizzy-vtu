<?php
namespace Semizzy\Addons\GiftCards\Models;
use Illuminate\Database\Eloquent\Model;
class GiftCardProvider extends Model { protected $table='gift_card_providers'; protected $guarded=[]; protected $casts=['capabilities'=>'array','enabled'=>'boolean','paused'=>'boolean']; }