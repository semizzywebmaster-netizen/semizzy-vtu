<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\ApiProvider;
use App\Models\Service;
use App\Models\ServiceProduct;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VtuTransaction;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $term = trim($data['q']);
        $like = '%' . addcslashes($term, '%_\\\\') . '%';
        $user = $request->user();
        $results = [];

        if ($user->hasPermission('users.view')) {
            User::query()
                ->where(function ($query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                })
                ->orderBy('name')->limit(5)->get(['id', 'name', 'username', 'email', 'phone'])
                ->each(function (User $user) use (&$results): void {
                    $results[] = [
                        'type' => 'User',
                        'title' => $user->name,
                        'description' => '@' . $user->username . ' · ' . $user->email,
                        'url' => '/admin/users?search=' . urlencode((string) $user->username),
                    ];
                });
        }

        if ($user->hasPermission('providers.view')) {
            ApiProvider::query()
                ->where(fn ($query) => $query->where('display_name', 'like', $like)->orWhere('identifier', 'like', $like))
                ->orderBy('display_name')->limit(5)->get(['id', 'display_name', 'identifier', 'verification_status'])
                ->each(function (ApiProvider $provider) use (&$results): void {
                    $results[] = [
                        'type' => 'API Provider',
                        'title' => $provider->display_name,
                        'description' => $provider->identifier . ' · ' . str_replace('_', ' ', $provider->verification_status),
                        'url' => '/admin/providers/' . $provider->id . '/catalogue',
                    ];
                });
        }

        if ($user->hasPermission('catalogue.view')) {
            Service::query()
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('key', 'like', $like))
                ->with('category:id,name')->orderBy('name')->limit(5)->get(['id', 'category_id', 'name', 'key'])
                ->each(function (Service $service) use (&$results): void {
                    $results[] = [
                        'type' => 'Service',
                        'title' => $service->name,
                        'description' => ($service->category?->name ? $service->category->name . ' · ' : '') . $service->key,
                        'url' => '/admin/catalogue?search=' . urlencode($service->key),
                    ];
                });

            ServiceProduct::query()
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('key', 'like', $like)->orWhere('provider_product_id', 'like', $like))
                ->with('service:id,name')->orderBy('name')->limit(5)->get(['id', 'service_id', 'name', 'key', 'provider_product_id'])
                ->each(function (ServiceProduct $product) use (&$results): void {
                    $results[] = [
                        'type' => 'Catalogue product',
                        'title' => $product->name,
                        'description' => ($product->service?->name ? $product->service->name . ' · ' : '') . $product->key,
                        'url' => '/admin/catalogue?search=' . urlencode($product->key),
                    ];
                });
        }

        if ($user->hasPermission('help.manage') || $user->hasPermission('communications.manage')) {
            SupportTicket::query()
                ->where(fn ($query) => $query->where('reference', 'like', $like)->orWhere('subject', 'like', $like))
                ->orderByDesc('updated_at')->limit(5)->get(['id', 'reference', 'subject', 'status'])
                ->each(function (SupportTicket $ticket) use (&$results): void {
                    $results[] = [
                        'type' => 'Support ticket',
                        'title' => $ticket->subject,
                        'description' => $ticket->reference . ' · ' . str_replace('_', ' ', $ticket->status),
                        'url' => '/support/' . $ticket->id,
                    ];
                });
        }

        if ($user->hasPermission('vtu.transactions.view') && Schema::hasTable('vtu_transactions')) {
            VtuTransaction::query()
                ->where(fn ($query) => $query->where('reference', 'like', $like)
                    ->orWhere('provider_reference', 'like', $like)
                    ->orWhere('recipient', 'like', $like))
                ->with(['service:id,name'])
                ->latest('created_at')->limit(5)->get(['id', 'reference', 'provider_reference', 'status', 'service_id'])
                ->each(function (VtuTransaction $transaction) use (&$results): void {
                    $results[] = [
                        'type' => 'VTU transaction',
                        'title' => $transaction->reference,
                        'description' => ($transaction->service?->name ? $transaction->service->name . ' · ' : '') . str_replace('_', ' ', $transaction->status),
                        'url' => '/admin/vtu/transactions?search=' . urlencode($transaction->reference),
                    ];
                });
        }

        if ($user->hasPermission('audit.view')) {
            AuditEvent::query()
                ->where(fn ($query) => $query->where('request_id', 'like', $like)->orWhere('event', 'like', $like))
                ->latest('id')->limit(5)->get(['id', 'event', 'request_id', 'created_at'])
                ->each(function (AuditEvent $event) use (&$results): void {
                    $results[] = [
                        'type' => 'Audit event',
                        'title' => $event->event,
                        'description' => 'Request ' . $event->request_id,
                        'url' => '/admin/audit-events?request_id=' . urlencode($event->request_id),
                    ];
                });
        }

        if ($user->hasPermission('addons.view')) {
            Addon::query()->where('status', 'active')
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('identifier', 'like', $like))
                ->orderBy('name')->limit(5)->get(['identifier', 'name', 'version', 'status'])
                ->each(function (Addon $addon) use (&$results): void {
                    $results[] = [
                        'type' => 'Active addon',
                        'title' => $addon->name,
                        'description' => $addon->identifier . ' · v' . $addon->version,
                        'url' => '/admin/addons',
                    ];
                });
        }

        return response()->json([
            'query' => $term,
            'results' => array_slice($results, 0, 15),
        ])->header('Cache-Control', 'no-store, private');
    }
}
