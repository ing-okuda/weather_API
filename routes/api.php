<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WeatherController;

Route::get('/weather', [WeatherController::class, 'show']);
Route::post('/weather', [WeatherController::class, 'store']);
?>
