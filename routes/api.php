<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| User Management Routes
|--------------------------------------------------------------------------
*/
Route::get('/users', [UserController::class, 'getUsers']);
Route::delete('/users/{id}', [UserController::class, 'removeUser']);
