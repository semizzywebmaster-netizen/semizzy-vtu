<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Services\Cac\CacWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CacWebhookController extends Controller
{
    public function handle(Request $request, ApiProvider $provider, CacWebhookService $service)
    {
        try {
            return response()->json([
                'ok' => true,
                'data' => $service->handle($request, $provider),
            ]);
        } catch (RuntimeException $e) {
            $status = $e->getMessage() === 'Webhook authentication failed.' ? 401 : 422;
            Log::warning('CAC webhook rejected', [
                'provider_id' => $provider->id,
                'reason' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $status === 401 ? 'Webhook authentication failed.' : 'Invalid webhook request.',
            ], $status);
        } catch (\Throwable $e) {
            Log::error('CAC webhook processing failed', [
                'provider_id' => $provider->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Webhook could not be processed.',
            ], 500);
        }
    }
}
