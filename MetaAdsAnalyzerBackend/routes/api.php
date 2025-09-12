<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\UsersController;
use App\Http\Controllers\Login\LoginController;
use App\Http\Controllers\File\FileController;
use App\Http\Controllers\Dashboard\DashboardController;

Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout']);
Route::get('/users', [UsersController::class, 'getUser']);
Route::post('/users', [UsersController::class, 'createUser']);
Route::put('/users/{id}', [UsersController::class, 'editUser']);
Route::delete('/users/{id}', [UsersController::class, 'delete']);

Route::post('/upload', [FileController::class, 'upload']);


Route::get('dashboard', [DashboardController::class, 'index']);
Route::get('dashboard/monthly', [DashboardController::class, 'monthly']);
Route::get('dashboard/adsets', [DashboardController::class, 'adsets']);

Route::post('/dashboard/generate-analysis', [DashboardController::class, 'generateAnalysis']);
