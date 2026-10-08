<?php

namespace Semizzy\Addons\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProductMedia extends Model
{
    protected $table = 'marketplace_product_media';
    protected $guarded = [];

    protected $appends = ['embed_url'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getEmbedUrlAttribute(): ?string
    {
        if (($this->media_type ?? null) !== 'video' || empty($this->url)) {
            return null;
        }

        $url = (string) $this->url;
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            $id = $query['v'] ?? null;
            if (!$id && str_starts_with($path, 'shorts/')) $id = substr($path, 7);
            if (!$id && str_starts_with($path, 'embed/')) $id = substr($path, 6);
            return $id ? 'https://www.youtube-nocookie.com/embed/'.rawurlencode($id) : null;
        }

        if ($host === 'youtu.be') {
            $id = explode('/', $path)[0] ?? '';
            return $id !== '' ? 'https://www.youtube-nocookie.com/embed/'.rawurlencode($id) : null;
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)) {
            $segments = array_values(array_filter(explode('/', $path)));
            $id = end($segments);
            return $id && ctype_digit($id) ? 'https://player.vimeo.com/video/'.$id : null;
        }

        if ($host === 'player.vimeo.com' && str_starts_with($path, 'video/')) {
            return $url;
        }

        return null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }
}
