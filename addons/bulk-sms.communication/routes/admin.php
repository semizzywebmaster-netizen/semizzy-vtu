<?php
use Illuminate\Support\Facades\Route; use Semizzy\Addons\BulkSms\Http\Controllers\AdminBulkSmsController;
Route::middleware(['auth','verified','role:ADMIN,STAFF,SUPPORT','ensure.addon:bulk-sms.communication'])->prefix('admin/bulk-sms')->group(function(){Route::get('/',[AdminBulkSmsController::class,'index'])->middleware('permission:bulk_sms.view');Route::patch('/sender-ids/{sender}',[AdminBulkSmsController::class,'senderStatus'])->middleware('permission:bulk_sms.manage');});
