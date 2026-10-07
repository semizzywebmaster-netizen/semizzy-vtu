<?php

use Illuminate\Support\Facades\Route;
use Semizzy\Addons\TravelTickets\Http\Controllers\TravelTicketsController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/travel-tickets', [TravelTicketsController::class, 'index'])
        ->middleware('permission:travel.view')
        ->name('travel.index');

    Route::get('/travel-tickets/bookings', [TravelTicketsController::class, 'bookings'])
        ->middleware('permission:travel.view')
        ->name('travel.bookings');

    Route::post('/travel-tickets/book', [TravelTicketsController::class, 'store'])
        ->middleware('permission:travel.book')
        ->middleware('transaction.pin')
        ->name('travel.book');

    Route::post('/travel-tickets/bookings/{booking}/requery', [TravelTicketsController::class, 'requery'])
        ->middleware('permission:travel.book')
        ->middleware('transaction.pin')
        ->name('travel.requery');

    Route::post('/travel-tickets/bookings/{booking}/cancel', [TravelTicketsController::class, 'cancel'])
        ->middleware('permission:travel.book')
        ->middleware('transaction.pin')
        ->name('travel.cancel');
});