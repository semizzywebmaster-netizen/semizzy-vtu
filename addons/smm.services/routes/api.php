<?php
use Illuminate\Support\Facades\Route;
Route::middleware(['api','auth:sanctum','ensure.addon:smm.services'])->prefix('api/v1/smm')->group(function(){
    Route::get('/services', fn() => response()->json(['data'=>[]]));
});