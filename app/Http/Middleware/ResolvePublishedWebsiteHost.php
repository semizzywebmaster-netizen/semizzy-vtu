<?php

namespace App\Http\Middleware;

use Addons\VtuWebsiteBuilder\Services\WebsiteBuilderService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublishedWebsiteHost
{
    private const RESERVED_PREFIXES = [
        'admin',
        'api',
        'login',
        'register',
        'logout',
        'dashboard',
        'profile',
        'kyc',
        'transactions',
        'wallet',
        'send-money',
        'withdraw',
        'analytics',
        'notifications',
        'realtime',
        'help',
        'support',
        'setup',
        'email',
        'security',
        'manifest.webmanifest',
        'up',
        'website-builder',
        'sites',
    ];

    public function __construct(private WebsiteBuilderService $service)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $firstSegment = strtolower((string) strtok($path, '/'));
        $host = strtolower(rtrim($request->getHost(), '.'));

        if ($firstSegment !== '' && in_array($firstSegment, self::RESERVED_PREFIXES, true)) {
            return $next($request);
        }

        if ($path !== '' && str_contains($path, '.')) {
            return $next($request);
        }

        $site = $this->service->resolvePublishedByHost($host);
        if (!$site) {
            return $next($request);
        }

        $site->load(['pages' => fn ($query) => $query
            ->where('status', 'published')
            ->orderBy('sort_order')]);

        $slug = $path === '' ? null : trim($path, '/');
        if ($slug !== null && !preg_match('/^[A-Za-z0-9-]+$/', $slug)) {
            return $next($request);
        }

        $target = $slug === null
            ? $site->pages->firstWhere('is_home', true)
            : $site->pages->firstWhere('slug', $slug);

        if (!$target) {
            return $next($request);
        }

        return Inertia::render('WebsitePublic', [
            'site' => $site->only(['id', 'name', 'slug', 'template_key', 'settings']) + [
                'pages' => $site->pages->map(fn ($page) => $page->only([
                    'id', 'title', 'slug', 'is_home', 'sort_order'
                ]))->values()->all(),
            ],
            'page' => $target->only(['id', 'title', 'slug', 'content', 'seo']),
            'preview' => false,
        ]);
    }
}
