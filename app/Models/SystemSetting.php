<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'is_secret'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
