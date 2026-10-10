<?php
namespace Addons\CommunicationWhatsapp\Models;

use Addons\CommunicationWhatsapp\Models\Consent;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait UserCommunicationRelations
{
 public function communicationConsents(): HasMany
 {
  return $this->hasMany(Consent::class,'user_id');
 }
}
