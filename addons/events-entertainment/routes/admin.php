<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\AdminEventController;
Route::middleware(['web','auth','ensure.addon:events.entertainment'])->prefix('admin/events')->group(function(){Route::get('/',[AdminEventController::class,'index'])->middleware('permission:events.manage')->name('admin.events.index');Route::post('/events',[AdminEventController::class,'store'])->middleware('permission:events.organize')->name('admin.events.store');Route::post('/venues',[AdminEventController::class,'venue'])->middleware('permission:events.manage')->name('admin.events.venues.store');Route::post('/events/{event}/tickets',[AdminEventController::class,'ticket'])->middleware('permission:events.tickets.manage')->name('admin.events.tickets.store');});
