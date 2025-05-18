<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;

Route::post('/reports', [ReportController::class, 'store']);            // create report

Route::get('/reports/{id}/status', [ReportController::class, 'status']); // check status

Route::get('/reports/{id}', [ReportController::class, 'show']);          // get final report

Route::post('/auth/google', [AuthController::class, 'google']);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});
