<?php
use Addons\CommunicationWhatsapp\Http\Controllers\WhatsAppStatusController;
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationCenterController;
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationMessageController;
use Addons\CommunicationWhatsapp\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/communication/whatsapp/{provider}',[WhatsAppWebhookController::class,'verify'])->name('communication.whatsapp.webhook.verify');
Route::post('/webhooks/communication/whatsapp/{provider}',[WhatsAppWebhookController::class,'receive'])->name('communication.whatsapp.webhook.receive');
Route::post('/webhooks/communication/whatsapp/{provider}/status',[WhatsAppStatusController::class,'receive'])->name('communication.whatsapp.webhook.status');

Route::middleware(['auth','verified','permission:communication.send'])->group(function () {
 Route::post('/communication/whatsapp/send',[CommunicationMessageController::class,'sendWhatsApp'])->name('communication.whatsapp.send');
});

Route::middleware(['auth','verified','permission:communication.view'])->group(function () {
 Route::get('/communication/conversations',[CommunicationCenterController::class,'conversations'])->name('communication.conversations');
 Route::get('/communication/conversations/{conversation}',[CommunicationCenterController::class,'show'])->name('communication.conversations.show');
});
Route::middleware(['auth','verified','permission:communication.conversations.manage'])->group(function () {
 Route::post('/communication/conversations/{conversation}/reply',[CommunicationCenterController::class,'reply'])->name('communication.conversations.reply');
});
