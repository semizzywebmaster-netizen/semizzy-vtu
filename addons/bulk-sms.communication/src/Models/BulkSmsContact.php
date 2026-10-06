<?php
namespace Semizzy\Addons\BulkSms\Models;
use Illuminate\Database\Eloquent\Model;
class BulkSmsContact extends Model {protected $table='bulk_sms_contacts';protected $guarded=[];protected $casts=['subscribed'=>'boolean'];}
