<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\EventCheckoutController;
Route::middleware(['web','ensure.addon:events.entertainment'])->group(function(){Route::get('/events/tickets/{ticketType}/checkout',[EventCheckoutController::class,'create'])->middleware('permission:events.view')->name('events.checkout');Route::post('/events/tickets/{ticketType}/checkout',[EventCheckoutController::class,'store'])->middleware('auth')->name('events.checkout.store');});
