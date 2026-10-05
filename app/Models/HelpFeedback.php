<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpFeedback extends Model
{
    protected $fillable = ['user_id','article_id','helpful','comment','context_key'];

    protected function casts(): array { return ['helpful' => 'boolean']; }

    public function article(): BelongsTo { return $this->belongsTo(HelpArticle::class, 'article_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}