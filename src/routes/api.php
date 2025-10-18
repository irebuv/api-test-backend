<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');

Route::resource('/businesses', BusinessController::class);
Route::post("/businesses/request/{editingId}", [BusinessController::class, 'request']);
Route::post("/businesses/markAsRead", [BusinessController::class, 'markAsRead']);
