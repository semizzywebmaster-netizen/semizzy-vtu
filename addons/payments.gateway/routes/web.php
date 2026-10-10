<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureAddonActive;

Route::middleware(['auth', 'verified', EnsureAddonActive::class.':payments.gateway'])
    ->prefix('payments')->name('payments.')
    ->group(function (): void {
        Route::get('/', fn () => redirect('/dashboard'))->name('index');
        Route::get('/manual-deposit', [ManualDepositController::class, 'index'])->middleware('permission:payments.manual_deposits.create')->name('manual-deposit');
        Route::post('/manual-deposit', [ManualDepositController::class, 'store'])->middleware('permission:payments.manual_deposits.create')->name('manual-deposit.store');
        Route::get('/manual-deposit/{deposit}/proof', [ManualDepositController::class, 'proof'])->middleware('permission:payments.manual_deposits.create')->name('manual-deposit.proof');
    });
