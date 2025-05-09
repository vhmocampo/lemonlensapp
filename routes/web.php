<?php

use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-mongodb', function () {
    dump(Vehicle::first()->make);
    dump(Vehicle::first()->model);
    dump(Vehicle::first()->year);
});
