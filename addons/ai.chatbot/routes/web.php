<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\AIChatbot\Http\Controllers\AIChatbotAdminController;
use Semizzy\Addons\AIChatbot\Http\Controllers\AIChatbotController;

Route::middleware(['auth','verified','role:ADMIN,STAFF,SUPPORT','ensure.addon:ai.chatbot'])->prefix('admin/ai-chatbot')->group(function():void{
 Route::get('/',[AIChatbotAdminController::class,'index'])->middleware('permission:ai_chatbot.settings.manage')->name('admin.ai-chatbot.index');
 Route::get('/conversations',[AIChatbotAdminController::class,'conversations'])->middleware('permission:ai_chatbot.conversations.view')->name('admin.ai-chatbot.conversations');
 Route::post('/providers',[AIChatbotAdminController::class,'storeProvider'])->middleware('permission:ai_chatbot.providers.manage')->name('admin.ai-chatbot.providers.store');
 Route::put('/providers/{provider}',[AIChatbotAdminController::class,'updateProvider'])->whereNumber('provider')->middleware('permission:ai_chatbot.providers.manage')->name('admin.ai-chatbot.providers.update');
 Route::post('/providers/{provider}/test',[AIChatbotAdminController::class,'testProvider'])->whereNumber('provider')->middleware('permission:ai_chatbot.providers.manage')->name('admin.ai-chatbot.providers.test');
 Route::delete('/providers/{provider}',[AIChatbotAdminController::class,'deleteProvider'])->whereNumber('provider')->middleware('permission:ai_chatbot.providers.manage')->name('admin.ai-chatbot.providers.delete');
 Route::put('/settings',[AIChatbotAdminController::class,'saveSettings'])->middleware('permission:ai_chatbot.settings.manage')->name('admin.ai-chatbot.settings');
 Route::post('/knowledge',[AIChatbotAdminController::class,'storeKnowledge'])->middleware('permission:ai_chatbot.knowledge.manage')->name('admin.ai-chatbot.knowledge.store');
 Route::put('/knowledge/{item}',[AIChatbotAdminController::class,'updateKnowledge'])->whereNumber('item')->middleware('permission:ai_chatbot.knowledge.manage')->name('admin.ai-chatbot.knowledge.update');
 Route::delete('/knowledge/{item}',[AIChatbotAdminController::class,'deleteKnowledge'])->whereNumber('item')->middleware('permission:ai_chatbot.knowledge.manage')->name('admin.ai-chatbot.knowledge.delete');
});
Route::middleware(['ensure.addon:ai.chatbot'])->group(function():void{
 Route::get('/ai-chatbot/config',[AIChatbotController::class,'config'])->name('ai-chatbot.config');
 Route::post('/ai-chatbot/conversations',[AIChatbotController::class,'createConversation'])->middleware('throttle:20,1')->name('ai-chatbot.conversations.store');
 Route::get('/ai-chatbot/conversations',[AIChatbotController::class,'listConversations'])->middleware(['auth','permission:ai_chatbot.use'])->name('ai-chatbot.conversations.index');
 Route::get('/ai-chatbot/conversations/{uuid}',[AIChatbotController::class,'showConversation'])->name('ai-chatbot.conversations.show');
 Route::post('/ai-chatbot/conversations/{uuid}/messages',[AIChatbotController::class,'sendMessage'])->middleware('throttle:20,1')->name('ai-chatbot.messages.store');
 Route::delete('/ai-chatbot/conversations/{uuid}',[AIChatbotController::class,'deleteConversation'])->name('ai-chatbot.conversations.delete');
 Route::post('/ai-chatbot/conversations/{uuid}/escalate',[AIChatbotController::class,'escalate'])->middleware('throttle:5,1')->name('ai-chatbot.conversations.escalate');
});
