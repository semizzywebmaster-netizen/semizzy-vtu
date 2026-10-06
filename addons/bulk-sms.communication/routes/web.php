<?php
use Illuminate\Support\Facades\Route; use Semizzy\Addons\BulkSms\Http\Controllers\BulkSmsController;
Route::middleware(['auth','verified','ensure.addon:bulk-sms.communication','permission:bulk_sms.view'])->get('/bulk-sms',[BulkSmsController::class,'index']);
