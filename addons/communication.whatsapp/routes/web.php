<?php
use Addons\CommunicationWhatsapp\Http\Controllers\WhatsAppStatusController;
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationCenterController;
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationTemplateController;
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationConsentController;
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationMessageController;
use Addons\CommunicationWhatsapp\Http\Controllers\WhatsAppWebhookController;
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationProviderController;
use Addons\CommunicationWhatsapp\Http\Controllers\CommunicationCampaignController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/communication/whatsapp/{provider}',[WhatsAppWebhookController::class,'verify'])->name('communication.whatsapp.webhook.verify');
Route::post('/webhooks/communication/whatsapp/{provider}',[WhatsAppWebhookController::class,'receive'])->name('communication.whatsapp.webhook.receive');
Route::post('/webhooks/communication/whatsapp/{provider}/status',[WhatsAppStatusController::class,'receive'])->name('communication.whatsapp.webhook.status');

Route::middleware(['auth','verified','permission:communication.send'])->group(function () {
 Route::post('/communication/whatsapp/send',[CommunicationMessageController::class,'sendWhatsApp'])->name('communication.whatsapp.send');
});

Route::middleware(['auth','verified','permission:communication.view'])->group(function () { Route::get('/communication',[\Addons\CommunicationWhatsapp\Http\Controllers\CommunicationCenterController::class,'page'])->name('communication.page');
 Route::get('/communication/conversations',[CommunicationCenterController::class,'conversations'])->name('communication.conversations');
 Route::get('/communication/delivery-attempts',[CommunicationCenterController::class,'attempts'])->name('communication.delivery-attempts');
 Route::get('/communication/conversations/{conversation}',[CommunicationCenterController::class,'show'])->name('communication.conversations.show');
});
Route::middleware(['auth','verified','permission:communication.conversations.manage'])->group(function () {
 Route::post('/communication/conversations/{conversation}/reply',[CommunicationCenterController::class,'reply'])->name('communication.conversations.reply');
});

Route::middleware(['auth','verified','permission:communication.view'])->group(function () {
 Route::get('/communication/templates',[CommunicationTemplateController::class,'index'])->name('communication.templates');
 Route::post('/communication/templates/{template}/render',[CommunicationTemplateController::class,'render'])->name('communication.templates.render');
 Route::get('/communication/consents',[CommunicationConsentController::class,'mine'])->name('communication.consents.mine');
});
Route::middleware(['auth','verified','permission:communication.templates.manage'])->group(function () {
 Route::post('/communication/templates',[CommunicationTemplateController::class,'store'])->name('communication.templates.store');
});
Route::middleware(['auth','verified','permission:communication.consent.manage'])->group(function () {
 Route::post('/communication/consents',[CommunicationConsentController::class,'set'])->name('communication.consents.set');
});


Route::middleware(['auth','verified','permission:communication.campaigns.manage'])->group(function () {
 Route::get('/communication/campaigns',[CommunicationCampaignController::class,'index'])->name('communication.campaigns');
 Route::post('/communication/campaigns',[CommunicationCampaignController::class,'store'])->name('communication.campaigns.store');
 Route::get('/communication/campaigns/{campaign}',[CommunicationCampaignController::class,'show'])->name('communication.campaigns.show');
 Route::post('/communication/campaigns/{campaign}/run',[CommunicationCampaignController::class,'run'])->name('communication.campaigns.run');
 Route::post('/communication/campaigns/{campaign}/pause',[CommunicationCampaignController::class,'pause'])->name('communication.campaigns.pause');
 Route::post('/communication/campaigns/{campaign}/cancel',[CommunicationCampaignController::class,'cancel'])->name('communication.campaigns.cancel');
});

Route::middleware(['auth','verified','permission:communication.providers.manage'])->group(function () {
 Route::get('/admin/communication/providers',[CommunicationProviderController::class,'index']);
 Route::get('/admin/communication',[CommunicationProviderController::class,'page'])->name('admin.communication');
 Route::post('/admin/communication/providers',[CommunicationProviderController::class,'store']);
 Route::patch('/admin/communication/providers/{provider}',[CommunicationProviderController::class,'update']);
 Route::post('/admin/communication/providers/{provider}/test',[CommunicationProviderController::class,'test']);
});
