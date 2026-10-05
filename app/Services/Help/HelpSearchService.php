<?php

namespace App\Services\Help;

use App\Models\HelpArticle;
use Illuminate\Support\Str;

class HelpSearchService
{
    public function search(?string $query, ?string $contextKey = null, int $limit = 8)
    {
        $query = trim((string) $query);
        $terms = collect(preg_split('/\\s+/', Str::lower($query), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($term) => mb_strlen($term) >= 2)
            ->take(12);

        return HelpArticle::query()
            ->where('published', true)
            ->when($contextKey, fn ($q) => $q->where(function ($q) use ($contextKey) {
                $q->whereNull('context_key')->orWhere('context_key', $contextKey);
            }))
            ->when($terms->isNotEmpty(), function ($q) use ($terms) {
                $q->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $like = '%'.$term.'%';
                        $q->orWhere('title', 'like', $like)
                            ->orWhere('excerpt', 'like', $like)
                            ->orWhere('content', 'like', $like)
                            ->orWhereJsonContains('tags', $term);
                    }
                });
            })
            ->orderByDesc(fn ($q) => 0)
            ->orderBy('sort_order')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    public function related(?string $contextKey = null, int $limit = 6)
    {
        return HelpArticle::query()->where('published', true)
            ->when($contextKey, fn ($q) => $q->where('context_key', $contextKey))
            ->orderBy('sort_order')->orderByDesc('views')->limit($limit)->get();
    }
}