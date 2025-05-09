<?php

use Illuminate\Support\Facades\Route;

Route::post('/reports', [ReportController::class, 'store']);            // create report
Route::get('/reports/{id}/status', [ReportController::class, 'status']); // check status
Route::get('/reports/{id}', [ReportController::class, 'show']);          // get final report
