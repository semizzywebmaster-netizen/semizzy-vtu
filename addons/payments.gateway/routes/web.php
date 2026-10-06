<?php

use IlluminateSupportFacadesRoute;
use AppHttpMiddlewareEnsureAddonActive;

Route::middleware(['auth', 'verified', EnsureAddonActive::class.':payments.gateway'])
    ->prefix('payments')->name('payments.')
    ->group(function (): void {
        Route::get('/', fn () => redirect('/dashboard'))->name('index');
    });
