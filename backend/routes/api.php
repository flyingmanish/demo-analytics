<?php

use App\Http\Controllers\Api\AnalyticsSummaryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::post('/events', [EventController::class, 'store'])->middleware('throttle:120,1');
Route::get('/analytics/summary', [AnalyticsSummaryController::class, 'show']);
Route::get('/menu', [MenuController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
