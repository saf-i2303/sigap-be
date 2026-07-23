<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Api\User\ComplaintController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\Admin\AdminComplaintController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminController;
use App\Http\Controllers\Api\Petugas\PetugasController;
use Illuminate\Support\Facades\Route;

// ── Public ────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::get('/categories', [CategoryController::class, 'index']);

// ── Authenticated (semua role) ────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get('/notifications',              [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read',  [NotificationController::class, 'markAsRead']);
});

// ── User ──────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:user'])->group(function () {
    Route::get('/laporan',              [ComplaintController::class, 'index']);
    Route::post('/laporan',             [ComplaintController::class, 'store']);
    Route::get('/laporan/{id}',         [ComplaintController::class, 'show']);
    Route::post('/laporan/{id}',        [ComplaintController::class, 'update']);
    Route::delete('/laporan/{id}',      [ComplaintController::class, 'destroy']);
    Route::get('/laporan/{id}/komentar',[ComplaintController::class, 'comments']);
});

// ── Admin ─────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/laporan',                        [AdminComplaintController::class, 'index']);
    Route::get('/laporan/{id}',                   [AdminComplaintController::class, 'show']);
    Route::patch('/laporan/{id}/priority',        [AdminComplaintController::class, 'setPriority']);
    Route::patch('/laporan/{id}/status',          [AdminComplaintController::class, 'updateStatus']);
    Route::post('/laporan/{id}/komentar',         [AdminComplaintController::class, 'addComment']);
    Route::post('/laporan/{id}/respon',           [AdminComplaintController::class, 'addResponse']);
    Route::put('/komentar/{commentId}',           [AdminComplaintController::class, 'updateComment']);
    Route::delete('/komentar/{commentId}',        [AdminComplaintController::class, 'destroyComment']);
});

// ── SuperAdmin ────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:superadmin'])->prefix('superadmin')->group(function () {
    Route::get('/users',          [SuperAdminController::class, 'index']);
    Route::post('/users',         [SuperAdminController::class, 'store']);
    Route::patch('/users/{id}',   [SuperAdminController::class, 'update']);
    Route::delete('/users/{id}',  [SuperAdminController::class, 'destroy']);
    Route::get('/laporan',        [SuperAdminController::class, 'complaints']);
    Route::get('/statistik',      [SuperAdminController::class, 'statistics']);
    Route::get('/logs',           [SuperAdminController::class, 'logs']);

    Route::get('/categories',         [CategoryController::class, 'indexAdmin']);
    Route::post('/categories',        [CategoryController::class, 'store']);
    Route::patch('/categories/{id}',  [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});

// ── Petugas ───────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:petugas'])->prefix('petugas')->group(function () {
    Route::get('/me',                         [PetugasController::class, 'me']);
    Route::get('/laporan',                    [PetugasController::class, 'index']);
    Route::get('/laporan/{id}',               [PetugasController::class, 'show']);
    Route::post('/laporan/{id}/progress',     [PetugasController::class, 'addProgress']);
});