<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpArticle extends Model
{
    protected $fillable = ['type','title','slug','excerpt','content','category','context_key','tags','published','sort_order','views'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'published' => 'boolean'];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(HelpQuestion::class, 'resolved_article_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(HelpFeedback::class, 'article_id');
    }
}