<?php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Semizzy\Addons\P2p\Models\P2pTransfer;
Route::middleware(['web','auth','ensure.addon:p2p.transfers','permission:p2p.manage'])->group(function(){
 Route::get('/admin/p2p/transfers',fn()=>Inertia::render('Admin/P2p/Transfers',['transfers'=>P2pTransfer::with('sender','recipient')->latest()->paginate(50)]))->name('admin.p2p.transfers');
});