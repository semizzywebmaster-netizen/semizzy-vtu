<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['web','auth','ensure.addon:smm.services','permission:smm.view'])->group(function(){
    Route::get('/admin/smm', fn() => inertia('Admin/SMM/Index'))->name('admin.smm.index');
});