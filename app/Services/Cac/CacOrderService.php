<?php

namespace App\Services\Cac;

use App\Models\Addon;
use App\Models\CacOrder;
use App\Models\CacServiceProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CacOrderService
{
    public function create(int $userId, CacServiceProduct $product, array $payload, ?string $idempotencyKey = null): CacOrder
    {
        if (!Addon::query()->where('identifier', 'cac.business-services')->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['service' => 'CAC Services addon is not active.']);
        }

        if (!$product->enabled) {
            throw ValidationException::withMessages(['service' => 'This CAC service is currently unavailable.']);
        }

        $this->validateRequirements($product, $payload);
        $key = $idempotencyKey ?: 'cac_'.Str::uuid();
        ksort($payload);

        return DB::transaction(function () use ($userId, $product, $payload, $key) {
            $existing = CacOrder::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                $existingPayload = (array) $existing->request_payload;
                ksort($existingPayload);
                if ((int) $existing->user_id !== $userId || (int) $existing->cac_service_product_id !== (int) $product->id || $existingPayload !== $payload) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different CAC order.']);
                }
                return $existing;
            }

            $amount = (int) ($product->selling_price_minor ?? 0);
            if ($amount < 0) {
                throw ValidationException::withMessages(['service' => 'Invalid CAC service price configuration.']);
            }
            $currency = strtoupper($product->currency ?: 'NGN');

            return CacOrder::create([
                'uuid' => (string) Str::uuid(),
                'reference' => 'CAC-'.strtoupper(Str::random(20)),
                'user_id' => $userId,
                'service_type' => $product->service_type,
                'status' => 'pending_review',
                'idempotency_key' => $key,
                'customer_name' => $payload['customer_name'] ?? null,
                'business_name' => $payload['business_name'] ?? null,
                'company_type' => $payload['company_type'] ?? null,
                'amount_minor' => $amount,
                'fee_minor' => 0,
                'total_minor' => $amount,
                'currency' => $currency,
                'request_payload' => $payload,
                'metadata' => ['service_product_id' => $product->id],
            ]);
        });
    }

    private function validateRequirements(CacServiceProduct $product, array $payload): void
    {
        $requirements = (array) $product->requirements;
        $missing = [];
        foreach ($requirements as $field => $rule) {
            if ($rule === true && (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '')) {
                $missing[$field] = 'This field is required for the selected CAC service.';
            }
        }
        if ($missing) {
            throw ValidationException::withMessages($missing);
        }
    }
}
