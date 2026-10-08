<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\EventPaymentApiController;
Route::middleware(['auth:sanctum','ensure.addon:events.entertainment'])->prefix('api/v1/events')->group(function(){Route::get('/health',fn()=>response()->json(['ok'=>true]))->middleware('permission:events.view');Route::post('/orders/{order}/payment/confirm',[EventPaymentApiController::class,'confirm'])->middleware('permission:events.manage');});