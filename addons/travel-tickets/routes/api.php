<?php
use Illuminate\Support\Facades\Route;use Semizzy\Addons\TravelTickets\Http\Controllers\TravelTicketsController;use Semizzy\Addons\TravelTickets\Http\Controllers\TravelSearchController;
Route::middleware('auth:sanctum')->prefix('api/v1/travel-tickets')->group(function():void{
 Route::get('/services',[TravelTicketsController::class,'apiServices']);
 Route::post('/search',[TravelSearchController::class,'search']);
 Route::post('/book',[TravelTicketsController::class,'store']);
});