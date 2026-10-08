<?php

use Addons\VirtualCards\Http\Controllers\VirtualCardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','verified','ensure.addon:virtual.cards'])->group(function (): void {
    Route::get('/virtual-cards', [VirtualCardController::class, 'index'])
        ->middleware('permission:virtual-cards.view')
        ->name('virtual-cards.index');
    Route::post('/virtual-cards/request', [VirtualCardController::class, 'request'])
        ->middleware('permission:virtual-cards.manage')
        ->name('virtual-cards.request');
    Route::post('/virtual-cards/{card}/limit', [VirtualCardController::class, 'limit'])
        ->middleware('permission:virtual-cards.manage')
        ->name('virtual-cards.limit');
    Route::post('/virtual-cards/{card}/freeze', [VirtualCardController::class, 'freeze'])
        ->middleware('permission:virtual-cards.freeze')
        ->name('virtual-cards.freeze');
    Route::post('/virtual-cards/{card}/unfreeze', [VirtualCardController::class, 'unfreeze'])
        ->middleware('permission:virtual-cards.freeze')
        ->name('virtual-cards.unfreeze');
});
