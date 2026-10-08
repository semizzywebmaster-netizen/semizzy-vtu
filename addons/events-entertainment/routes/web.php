<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\EventController;
Route::middleware(['web','ensure.addon:events.entertainment'])->prefix('events')->group(function () {
 Route::get('/',[EventController::class,'index'])->middleware('permission:events.view')->name('events.index');
 Route::get('/{event}',[EventController::class,'show'])->middleware('permission:events.view')->name('events.show');
});
