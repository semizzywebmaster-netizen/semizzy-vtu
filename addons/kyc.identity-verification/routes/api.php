<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/kyc')->middleware(['auth', 'api.token', 'ensure.addon:kyc.identity-verification'])->group(function () {
    Route::get('/status', function (\Illuminate\Http\Request $request) {
        $application = \Semizzy\Addons\Kyc\Models\KycApplication::where('user_id', $request->user()->id)->latest('id')->first();
        return response()->json([
            'status' => $application?->status ?? 'not_started',
            'application_id' => $application?->id,
        ]);
    })->middleware('permission:kyc.submit');
});
