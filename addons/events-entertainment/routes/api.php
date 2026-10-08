<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\EventPaymentApiController;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\EventTicketController;
Route::middleware(['auth:sanctum','ensure.addon:events.entertainment'])->prefix('api/v1/events')->group(function(){Route::get('/health',fn()=>response()->json(['ok'=>true]))->middleware('permission:events.view');Route::post('/orders/{order}/payment/confirm',[EventPaymentApiController::class,'confirm'])->middleware('permission:events.manage');
Route::get('/tickets/{ticket}/qr',[EventTicketController::class,'qrPayload'])->middleware('permission:events.purchase');
Route::post('/tickets/{ticket}/check-in',[EventTicketController::class,'checkIn'])->middleware('permission:events.checkin.manage');});