<?php

namespace Semizzy\Addons\CryptoPayments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Semizzy\Addons\CryptoPayments\Services\CryptoFundingSettingsService;

class CryptoFundingSettingsAdminController
{
    public function show(CryptoFundingSettingsService $settings): JsonResponse
    {
        return response()->json(['data' => ['funding_fee_percent' => $settings->fundingFeePercent()]]);
    }

    public function update(Request $request, CryptoFundingSettingsService $settings): JsonResponse
    {
        $data = $request->validate(['funding_fee_percent' => ['required', 'numeric', 'min:0', 'max:100']]);
        $percent = $settings->setFundingFeePercent((float) $data['funding_fee_percent']);
        return response()->json(['data' => ['funding_fee_percent' => $percent]]);
    }
}
