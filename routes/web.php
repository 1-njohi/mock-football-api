<?php

use App\Http\Controllers\MockAdminController;
use App\Http\Controllers\MockApiController;
use Illuminate\Support\Facades\Route;

// Mock API-Football endpoints
Route::get('/fixtures', [MockApiController::class, 'fixtures']);
Route::get('/odds', [MockApiController::class, 'odds']);
Route::get('/leagues', [MockApiController::class, 'leagues']);
Route::get('/teams', [MockApiController::class, 'teams']);

// Admin controls
Route::prefix('/dev/mock-api/{token}')->group(function () {
    Route::get('/show', [MockAdminController::class, 'show']);
    Route::post('/reset', [MockAdminController::class, 'reset']);
    Route::post('/advance', [MockAdminController::class, 'advance']);
    Route::post('/fixtures/{id}/score', [MockAdminController::class, 'score']);
    Route::post('/fixtures/{id}/status', [MockAdminController::class, 'status']);
});