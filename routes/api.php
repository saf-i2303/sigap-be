<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Api\User\ComplaintController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\Admin\AdminComplaintController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminController;
use Illuminate\Support\Facades\Route;

// AUTH - publik
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// KATEGORI - publik
Route::get('/kategori', [CategoryController::class, 'index']);

// AUTH untuk semua role yang sudah login
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// USER
Route::middleware(['auth:sanctum', 'role:user'])->group(function () {
    Route::get('/laporan', [ComplaintController::class, 'index']);
    Route::post('/laporan', [ComplaintController::class, 'store']);
    Route::get('/laporan/{id}', [ComplaintController::class, 'show']);
    Route::delete('/laporan/{id}', [ComplaintController::class, 'destroy']);
    Route::get('/laporan/{id}/komentar', [ComplaintController::class, 'comments']);
    Route::get('/notifikasi', [NotificationController::class, 'index']);
    Route::patch('/notifikasi/{id}/read', [NotificationController::class, 'markAsRead']);
});

// ADMIN
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/laporan', [AdminComplaintController::class, 'index']);
    Route::get('/admin/laporan/{id}', [AdminComplaintController::class, 'show']);
    Route::patch('/admin/laporan/{id}/priority', [AdminComplaintController::class, 'setPriority']);
    Route::patch('/admin/laporan/{id}/status', [AdminComplaintController::class, 'updateStatus']);
    Route::post('/admin/laporan/{id}/komentar', [AdminComplaintController::class, 'addComment']);
    Route::post('/admin/laporan/{id}/respon', [AdminComplaintController::class, 'addResponse']);
});

// SUPERADMIN
Route::middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
    Route::get('/superadmin/users', [SuperAdminController::class, 'index']);
    Route::post('/superadmin/users', [SuperAdminController::class, 'store']);
    Route::patch('/superadmin/users/{id}', [SuperAdminController::class, 'update']);
    Route::delete('/superadmin/users/{id}', [SuperAdminController::class, 'destroy']);
    Route::get('/superadmin/laporan', [SuperAdminController::class, 'complaints']);
    Route::get('/superadmin/statistik', [SuperAdminController::class, 'statistics']);
    Route::get('/superadmin/logs', [SuperAdminController::class, 'logs']);
});