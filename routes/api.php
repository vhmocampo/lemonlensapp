<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;

Route::post('/reports', [ReportController::class, 'store']);            // create report

Route::get('/reports/{id}/status', [ReportController::class, 'status']); // check status

Route::get('/reports/{id}', [ReportController::class, 'show']);          // get final report

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'google']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Get authenticated user's information
    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    // Add other protected routes here
});
