<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureAddonActive;
use App\Http\Middleware\EnsureAddonActive;

Route::middleware(['auth', 'verified', EnsureAddonActive::class.':payments.gateway'])
    ->prefix('payments')->name('payments.')
    ->group(function (): void {
        Route::get('/', fn () => redirect('/dashboard'))->name('index');
    });
