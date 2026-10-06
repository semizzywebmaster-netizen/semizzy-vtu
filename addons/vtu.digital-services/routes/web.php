<?php

use App\Http\Controllers\VtuController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'ensure.addon:vtu.digital-services'])->group(function (): void {
    Route::get('/vtu', [VtuController::class, 'index'])->middleware('permission:vtu.view')->name('vtu.services');
});
