<?php
use Addons\WhatsAppBot\Http\Controllers\WhatsAppBotWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/api/v1/whatsapp-bot/webhook', [WhatsAppBotWebhookController::class,'verify'])->name('whatsapp-bot.webhook.verify');
Route::post('/api/v1/whatsapp-bot/webhook', [WhatsAppBotWebhookController::class,'receive'])->name('whatsapp-bot.webhook');
