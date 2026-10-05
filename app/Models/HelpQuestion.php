<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpQuestion extends Model
{
    protected $fillable = ['user_id','question','normalized_hash','context_key','answered','resolved_article_id'];

    protected function casts(): array
    {
        return ['answered' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function resolvedArticle(): BelongsTo { return $this->belongsTo(HelpArticle::class, 'resolved_article_id'); }
}