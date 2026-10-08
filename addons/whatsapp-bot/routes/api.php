<?php
use Addons\WhatsAppBot\Http\Controllers\WhatsAppBotWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/api/v1/whatsapp-bot/webhook', [WhatsAppBotWebhookController::class,'receive'])->name('whatsapp-bot.webhook');
