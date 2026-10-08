<?php

namespace App\Services\Vtu;

use App\Models\FinancialOperation;
use App\Models\VtuConversionRequest;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use App\Models\Service;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VtuConversionService
{
    public const TYPES = [
        'airtime_to_cash' => 'Airtime → Cash',
        'airtime_to_data' => 'Airtime → Data',
        'data_to_cash' => 'Data → Cash',
        'data_to_airtime' => 'Data → Airtime',
    ];

    public function __construct(private AuditLogger $audit) {}

    public function create(int $userId, array $data, ?UploadedFile $proof = null): VtuConversionRequest
    {
        $type = (string) ($data['conversion_type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException('Unsupported conversion type.');
        }

        $sourceMinor = $this->minor((string) ($data['source_amount'] ?? '0'));
        if ($sourceMinor <= 0) {
            throw new RuntimeException('Source amount must be greater than zero.');
        }

        $settings = $this->settingsFor($type);
        $rate = (float) ($settings['rate_percent'] ?? 0);
        if ($rate <= 0) throw new RuntimeException('This conversion service is not currently configured.');
        $feeMinor = (int) ($settings['fee_minor'] ?? 0);
        $targetMinor = max(0, (int) round($sourceMinor * $rate / 100) - $feeMinor);

        $proofPath = $proof?->store('vtu-conversions', 'public');

        return DB::transaction(function () use ($userId, $data, $type, $sourceMinor, $targetMinor, $feeMinor, $rate, $proofPath): VtuConversionRequest {
            $reference = 'CONV-' . strtoupper(Str::random(20));
            $request = VtuConversionRequest::create([
                'uuid'=>(string) Str::uuid(),
                'reference'=>$reference,
                'user_id'=>$userId,
                'conversion_type'=>$type,
                'network'=>isset($data['network']) ? strtolower(trim((string) $data['network'])) : null,
                'status'=>'pending',
                'source_amount_minor'=>(string) $sourceMinor,
                'target_amount_minor'=>(string) $targetMinor,
                'fee_minor'=>(string) $feeMinor,
                'currency'=>'NGN',
                'rate'=>$rate,
                'source_payload'=>[
                    'phone'=>$data['source_phone'] ?? null,
                    'network'=>$data['network'] ?? null,
                    'product'=>$data['source_product'] ?? null,
                    'quantity'=>$data['quantity'] ?? null,
                ],
                'target_payload'=>[
                    'phone'=>$data['target_phone'] ?? null,
                    'product'=>$data['target_product'] ?? null,
                    'data_plan'=>$data['data_plan'] ?? null,
                ],
                'proof_path'=>$proofPath,
                'metadata'=>[
                    'manual_workflow'=>true,
                    'rate_source'=>'admin_configuration',
                    'submitted_at'=>now()->toIso8601String(),
                ],
            ]);

            $this->audit->record('vtu.conversion.submitted', $request, [
                'reference'=>$reference,
                'conversion_type'=>$type,
                'source_amount_minor'=>(string) $sourceMinor,
            ]);

            return $request->fresh();
        });
    }

    public function settingsFor(string $type): array
    {
        $serviceId=Service::query()->where('key',$type)->value('id');
        if(!$serviceId)return ['rate_percent'=>0.0,'fee_minor'=>0];
        $rows=DB::table('vtu_service_configurations')->where('service_id',$serviceId)->whereIn('key',['conversion_rate_percent','conversion_fee_minor'])->get();
        $rateRow=$rows->firstWhere('key','conversion_rate_percent');$feeRow=$rows->firstWhere('key','conversion_fee_minor');
        return ['rate_percent'=>$rateRow?(float)data_get(json_decode($rateRow->value,true),'value',0):0.0,'fee_minor'=>$feeRow?(int)data_get(json_decode($feeRow->value,true),'value',0):0];
    }

    public function verify(VtuConversionRequest $request, int $operatorId, string $receivingAccount, ?string $note = null): VtuConversionRequest
    {
        return DB::transaction(function () use ($request, $operatorId, $receivingAccount, $note): VtuConversionRequest {
            $locked = VtuConversionRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (!in_array($locked->status, ['pending','under_review'], true)) {
                throw new RuntimeException('Only pending conversion requests can be verified.');
            }

            $locked->status='verified';
            $locked->operator_id=$operatorId;
            $locked->receiving_account=trim($receivingAccount);            $locked->operator_note=$note;
            $locked->verified_at=now();
            $locked->metadata=array_merge((array)$locked->metadata, ['verified_by'=>$operatorId]);
            $locked->save();

            $this->audit->record('vtu.conversion.verified', $locked, [
                'reference'=>$locked->reference,
                'operator_id'=>$operatorId,
                'receiving_account'=>$locked->receiving_account,
            ]);

            return $locked->fresh();
        });
    }

    public function approve(VtuConversionRequest $request, int $operatorId, ?string $note = null): VtuConversionRequest
    {
        return DB::transaction(function () use ($request, $operatorId, $note): VtuConversionRequest {
            $locked = VtuConversionRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== 'verified') {
                throw new RuntimeException('Only verified conversion requests can be approved.');
            }

            $wallet = WalletAccount::query()
                ->where('user_id', $locked->user_id)
                ->where('currency', $locked->currency)
                ->lockForUpdate()->first();

            if (!$wallet || $wallet->status !== 'active') {
                throw new RuntimeException('User wallet is not available.');
            }

            $amount = (string)$locked->target_amount_minor;
            $settlementKey = 'vtu-conversion:'.$locked->id.':credit';
            if (!WalletMovement::query()->where('wallet_account_id',$wallet->id)->where('operation_key',$settlementKey)->exists()) {
                $before = (string)$wallet->available_minor;
                $wallet->available_minor = $this->add($before, $amount);
                $wallet->save();

                WalletMovement::create([
                    'wallet_account_id'=>$wallet->id,
                    'operation_key'=>$settlementKey,
                    'reference'=>$locked->reference,
                    'type'=>'conversion_credit',
                    'amount_minor'=>$amount,
                    'currency'=>$wallet->currency,
                    'available_before_minor'=>$before,
                    'available_after_minor'=>(string)$wallet->available_minor,
                    'held_before_minor'=>(string)$wallet->held_minor,
                    'held_after_minor'=>(string)$wallet->held_minor,
                    'metadata'=>[
                        'conversion_request_id'=>$locked->id,
                        'conversion_type'=>$locked->conversion_type,
                        'manual_settlement'=>true,
                    ],
                ]);
            }

            $operation = $locked->financialOperation()->lockForUpdate()->first();
            if (!$operation) {
                $operation = FinancialOperation::create([
                    'uuid'=>(string)Str::uuid(),
                    'reference'=>$locked->reference,
                    'user_id'=>$locked->user_id,
                    'type'=>'vtu.conversion.credit',
                    'status'=>'completed',
                    'amount_minor'=>$amount,
                    'currency'=>$locked->currency,
                    'idempotency_key'=>$settlementKey,
                    'metadata'=>['conversion_request_id'=>$locked->id,'conversion_type'=>$locked->conversion_type],
                ]);
                $locked->financial_operation_id=$operation->id;
            }

            $locked->status='completed';
            $locked->operator_id=$operatorId;
            $locked->operator_note=$note ?: $locked->operator_note;
            $locked->approved_at=now();
            $locked->completed_at=now();
            $locked->settlement_key=$settlementKey;
            $locked->metadata=array_merge((array)$locked->metadata, [
                'settlement_applied'=>true,
                'settled_by'=>$operatorId,
                'settled_at'=>now()->toIso8601String(),
            ]);
            $locked->save();

            $this->audit->record('vtu.conversion.completed', $locked, [
                'reference'=>$locked->reference,
                'operator_id'=>$operatorId,
                'credited_minor'=>$amount,
            ]);

            return $locked->fresh();
        });
    }

    public function reject(VtuConversionRequest $request, int $operatorId, string $reason): VtuConversionRequest
    {
        return DB::transaction(function () use ($request, $operatorId, $reason): VtuConversionRequest {            $locked=VtuConversionRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (!in_array($locked->status,['pending','under_review','verified'],true)) {
                throw new RuntimeException('This conversion request cannot be rejected.');
            }

            $locked->status='rejected';
            $locked->operator_id=$operatorId;
            $locked->rejection_reason=trim($reason);
            $locked->completed_at=now();
            $locked->save();

            $this->audit->record('vtu.conversion.rejected',$locked,[
                'reference'=>$locked->reference,
                'operator_id'=>$operatorId,
                'reason'=>$locked->rejection_reason,
            ]);

            return $locked->fresh();
        });
    }

    private function minor(string $value): int
    {
        $value=trim($value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/',$value)) throw new RuntimeException('Invalid monetary amount.');
        [$whole,$fraction]=array_pad(explode('.',$value,2),2,'0');
        return ((int)$whole*100)+(int)str_pad($fraction,2,'0');
    }

    private function add(string $a,string $b): string
    {
        if(function_exists('bcadd')) return bcadd($a,$b,0);
        if(!ctype_digit($a)||!ctype_digit($b)||strlen($a)>17||strlen($b)>17) throw new RuntimeException('Large wallet amounts require BCMath.');
        return (string)((int)$a+(int)$b);
    }
}