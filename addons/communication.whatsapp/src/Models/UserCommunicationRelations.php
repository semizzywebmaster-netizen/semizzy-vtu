<?php
namespace App\Models;

use App\Models\Communication\Consent;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait UserCommunicationRelations
{
 public function communicationConsents(): HasMany
 {
  return $this->hasMany(Consent::class,'user_id');
 }
}
