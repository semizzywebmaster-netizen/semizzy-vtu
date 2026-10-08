<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth','ensure.addon:events.entertainment'])
    ->prefix('events')
    ->group(function () {
        Route::get('/', fn () => \Inertia\Inertia::render('Events/Index'))
            ->middleware('permission:events.view')
            ->name('events.index');
    });
