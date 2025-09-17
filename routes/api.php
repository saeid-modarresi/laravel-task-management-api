<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\NotificationController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
// Disable throttling in testing environment to avoid test interference
$throttleMiddleware = app()->environment('testing') ? [] : ['throttle:10,1'];

Route::post('/login', [AuthController::class, 'login'])->middleware($throttleMiddleware);
Route::post('/register', [AuthController::class, 'register'])->middleware($throttleMiddleware);

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

/*
|--------------------------------------------------------------------------
| Comment Management Routes (Nested under Tasks)
|--------------------------------------------------------------------------
*/
Route::apiResource('tasks.comments', CommentController::class)
    ->parameters(['tasks' => 'task', 'comments' => 'comment']);

/*
|--------------------------------------------------------------------------
| Notification Management Routes
|--------------------------------------------------------------------------
*/
Route::get('/users/{userId}/notifications', [NotificationController::class, 'index']);
Route::get('/users/{userId}/notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::patch('/users/{userId}/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
Route::patch('/users/{userId}/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
Route::delete('/users/{userId}/notifications/{notificationId}', [NotificationController::class, 'destroy']);
