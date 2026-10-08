<?php
use Addons\WhatsAppBot\Http\Controllers\WhatsAppBotVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
 Route::get('/whatsapp-bot', [WhatsAppBotVerificationController::class,'show'])->name('whatsapp-bot.show');
 Route::post('/whatsapp-bot/verify/send', [WhatsAppBotVerificationController::class,'send'])->middleware('throttle:3,10')->name('whatsapp-bot.verify.send');
 Route::post('/whatsapp-bot/verify', [WhatsAppBotVerificationController::class,'verify'])->middleware('throttle:10,10')->name('whatsapp-bot.verify');
});
