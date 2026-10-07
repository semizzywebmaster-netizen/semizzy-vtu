<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['web','auth','ensure.addon:smm.services'])->group(function(){
    Route::get('/smm', fn() => inertia('SMM/Index'))->middleware('permission:smm.view')->name('smm.index');
});