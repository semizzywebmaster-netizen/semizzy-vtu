<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\TravelTickets\Http\Controllers\TravelSearchController;
use Semizzy\Addons\TravelTickets\Http\Controllers\TravelTicketsController;

Route::middleware('auth:sanctum')->prefix('api/v1/travel-tickets')->group(function (): void {
    Route::get('/services', [TravelTicketsController::class, 'apiServices']);

    Route::post('/search', [TravelSearchController::class, 'search']);

    Route::post('/book', [TravelTicketsController::class, 'store'])
        ->middleware('permission:travel.book')
        ->middleware('transaction.pin');

    Route::post('/book/{booking}/requery', [TravelTicketsController::class, 'requery'])
        ->middleware('permission:travel.book')
        ->middleware('transaction.pin');

    Route::post('/book/{booking}/cancel', [TravelTicketsController::class, 'cancel'])
        ->middleware('permission:travel.book')
        ->middleware('transaction.pin');
});