<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceProduct;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\ProductPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ProductPublicationController extends Controller
{
    public function readiness(ServiceProduct $product, ProductPublicationService $publication): JsonResponse
    {
        return response()->json($publication->assess($product))
            ->header('Cache-Control', 'no-store, private');
    }

    public function publish(Request $request, ServiceProduct $product, ProductPublicationService $publication, AuditLogger $audit): JsonResponse|RedirectResponse
    {
        $result = $publication->publish($product, $request->user());

        if (! $result['published']) {
            try {
                $audit->record('catalogue.product.publication_blocked', $result['product'], [
                    'blockers' => $result['assessment']['blockers'],
                ], $request);
            } catch (\Throwable $exception) {
                report($exception);
            }

            return $request->expectsJson()
                ? response()->json([
                    'status' => 'blocked',
                    'message' => 'This product is not ready to be published.',
                    'blockers' => $result['assessment']['blockers'],
                    'assessment' => $result['assessment'],
                ], 422)
                : back()->with('error', 'This product is not ready to be published: ' . implode(' ', $result['assessment']['blockers']));
        }

        try {
            $audit->record('catalogue.product.published', $result['product'], [
                'provider_id' => $result['assessment']['provider']['id'] ?? null,
                'tier_quotes' => $result['assessment']['tier_quotes'],
            ], $request);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $request->expectsJson()
            ? response()->json(['status' => 'published', 'message' => 'Product added to My Services.', 'assessment' => $result['assessment']])
            : back()->with('success', 'Product added to My Services.');
    }

    public function unpublish(Request $request, ServiceProduct $product, ProductPublicationService $publication, AuditLogger $audit): JsonResponse|RedirectResponse
    {
        $updated = $publication->unpublish($product);
        try {
            $audit->record('catalogue.product.unpublished', $updated, [
                'service_id' => $updated->service_id,
                'reason' => 'Admin unpublish action',
            ], $request);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $request->expectsJson()
            ? response()->json(['status' => 'unpublished', 'message' => 'Product unpublished. Provider mappings and transaction history were preserved.'])
            : back()->with('success', 'Product unpublished. Provider mappings and transaction history were preserved.');
    }
}
