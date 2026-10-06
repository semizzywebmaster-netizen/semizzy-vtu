<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureAddonActive;
use App\Http\Middleware\EnsureAddonActive;

Route::middleware(['auth', 'verified', 'role:ADMIN,STAFF,SUPPORT', EnsureAddonActive::class.':payments.gateway'])
    ->prefix('admin/payments')->name('admin.payments.')
    ->group(function (): void {
        Route::get('/', fn () => response()->json(['addon' => 'payments.gateway']))->middleware('permission:payments.view')->name('index');
    });
