<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DryCleanerEnd\CategoryController;
use App\Http\Controllers\DryCleanerEnd\JobController as DCJobController;
use App\Http\Controllers\DryCleanerEnd\CustomerController;
use App\Http\Controllers\DryCleanerEnd\PaymentController;
use App\Http\Controllers\DryCleanerEnd\StatsController;
use App\Http\Controllers\DryCleanerEnd\SettingController;
use App\Http\Controllers\ClientEnd\JobController as ClientJobController;

// ============================================================
// PUBLIC ROUTES (no auth required)
// ============================================================

// Auth
Route::post('/auth/login',          [AuthController::class, 'login']);
Route::post('/auth/setup-password', [AuthController::class, 'setupPassword']);

// Paystack callback (redirects, not JSON)
Route::get('/client/payment/callback', [PaymentController::class, 'callback']);

// ============================================================
// PROTECTED: DRY CLEANER ROUTES
// ============================================================
Route::middleware(['auth:sanctum'])->prefix('drycleaner')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Categories (laundry item types)
    Route::get('/categories',         [CategoryController::class, 'index']);
    Route::post('/categories',        [CategoryController::class, 'store']);
    Route::put('/categories/{id}',    [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Jobs
    Route::get('/jobs',               [DCJobController::class, 'index']);   // list + filters
    Route::post('/jobs',              [DCJobController::class, 'store']);   // create intake
    Route::get('/jobs/{id}',          [DCJobController::class, 'show']);
    Route::post('/jobs/{id}/ready',   [DCJobController::class, 'markReady']); // done button

    // Payment — generate QR code link
    Route::post('/jobs/{id}/payment', [PaymentController::class, 'initiate']);

    // Customers
    Route::get('/customers/search',   [CustomerController::class, 'search']);
    Route::post('/customers',         [CustomerController::class, 'createNewCustomer']);

    // Stats
    Route::get('/stats',              [StatsController::class, 'index']);

    // Settings
    Route::get('/settings',           [SettingController::class, 'index']);
    Route::put('/settings/{key}',     [SettingController::class, 'update']);
});

// ============================================================
// PROTECTED: CLIENT ROUTES
// ============================================================
Route::middleware(['auth:sanctum'])->prefix('client')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::delete('/me',   [AuthController::class, 'destroy']);

    // Jobs
    Route::get('/jobs',        [ClientJobController::class, 'index']);
    Route::get('/jobs/{id}',   [ClientJobController::class, 'show']);
});
