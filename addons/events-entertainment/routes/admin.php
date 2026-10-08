<?php
use Illuminate\Support\Facades\Route;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\AdminEventController;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\AdminEventPaymentController;
use Semizzy\Addons\EventsEntertainment\Http\Controllers\EventOccurrenceController;
use Semizzy\Addons\EventsEntertainment\Models\{Event,EventOrganizer};
Route::middleware(['web','auth','ensure.addon:events.entertainment'])->prefix('admin/events')->group(function(){Route::get('/',[AdminEventController::class,'index'])->middleware('permission:events.manage')->name('admin.events.index');Route::post('/',[AdminEventController::class,'store'])->middleware('permission:events.organize')->name('admin.events.store');
 Route::post('/{event}/submit',[AdminEventController::class,'submit'])->middleware('permission:events.organize')->name('admin.events.submit');
 Route::post('/{event}/review',[AdminEventController::class,'review'])->middleware('permission:events.publish')->name('admin.events.review');
 Route::post('/organizers/{organizer}/submit',[AdminEventController::class,'submitOrganizer'])->middleware('permission:events.organize')->name('admin.events.organizers.submit');
 Route::post('/organizers/{organizer}/verify',[AdminEventController::class,'verifyOrganizer'])->middleware('permission:events.verify')->name('admin.events.organizers.verify');Route::post('/{event}/occurrences',[EventOccurrenceController::class,'store'])->middleware('permission:events.organize')->name('admin.events.occurrences.store');
 Route::patch('/occurrences/{occurrence}',[EventOccurrenceController::class,'update'])->middleware('permission:events.organize')->name('admin.events.occurrences.update');
 Route::post('/venues',[AdminEventController::class,'storeVenue'])->middleware('permission:events.organize')->name('admin.events.venues.store');Route::post('/ticket-types',[AdminEventController::class,'storeTicketType'])->middleware('permission:events.tickets.manage')->name('admin.events.ticket-types.store');Route::post('/orders/{order}/manual-payment',[AdminEventPaymentController::class,'confirm'])->middleware('permission:events.orders.view')->name('admin.events.orders.manual-payment');});