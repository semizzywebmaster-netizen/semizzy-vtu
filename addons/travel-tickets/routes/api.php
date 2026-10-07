<?php
use Illuminate\Support\Facades\Route;use Semizzy\Addons\TravelTickets\Http\Controllers\TravelTicketsController;
Route::middleware('auth:sanctum')->prefix('api/v1/travel-tickets')->group(function():void{
 Route::get('/services',[TravelTicketsController::class,'index']);Route::post('/book',[TravelTicketsController::class,'store']);
});