<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;

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

/*
|--------------------------------------------------------------------------
| Project Management Routes
|--------------------------------------------------------------------------
*/
Route::apiResource('projects', ProjectController::class);

/*
|--------------------------------------------------------------------------
| Task Management Routes
|--------------------------------------------------------------------------
*/
Route::apiResource('tasks', TaskController::class);
