<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MetadataController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;

// API Version 1
Route::prefix('v1')->group(function () {
    // Report generation routes
    Route::middleware(['sanctum.optional'])->group(function() {
        // Report generation routes
        Route::post('/reports', [ReportController::class, 'store']);     
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/{uuid}/status', [ReportController::class, 'status']); 
        Route::get('/reports/{uuid}', [ReportController::class, 'show']);     
    });

    // Authentication routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [AuthController::class, 'google']);

    // Metadata routes
    Route::get('/vehicle/makes', [MetadataController::class, 'makes']); // get all makes
    Route::get('/vehicle/models', [MetadataController::class, 'models']); // get models for a specific make
    Route::get('/vehicle/years', [MetadataController::class, 'years']); // get all years

    // Anonymous session assignment routes
    Route::get('/session', [AuthController::class, 'assignSession']);

    // API Documentation
    // The routes for documentation are automatically registered by L5-Swagger

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Get authenticated user's information
        Route::get('/me', [UserController::class, 'profile']);

        // Credit management routes
        Route::get('/me/credits/history', [UserController::class, 'creditHistory']);
        Route::get('/me/credits/stats', [UserController::class, 'creditStats']);

        // Add other protected routes here
    });
});
