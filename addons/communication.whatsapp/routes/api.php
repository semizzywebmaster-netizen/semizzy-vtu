<?php
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum','permission:communication.send'])->group(function () {
 Route::post('/communication/whatsapp/send',[CommunicationMessageController::class,'sendWhatsApp']);
});
