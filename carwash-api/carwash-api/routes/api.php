<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\JobOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

// Public auth routes.
Route::middleware('web')->prefix('auth')->group(function () {
    Route::post('/login',  [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('admin.auth');
    Route::get('/me',      [AuthController::class, 'me'])->middleware('admin.auth');
});

// Everything below here requires an authenticated admin session.
Route::middleware(['web','admin.auth'])->group(function () {

    // Customers.
    Route::get('/customers',     [CustomerController::class, 'index']);
    Route::post('/customers',    [CustomerController::class, 'store']);
    Route::get('/customers/{id}',    [CustomerController::class, 'show']);
    Route::put('/customers/{id}',    [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

    // Vehicles.
    Route::get('/vehicles',          [VehicleController::class, 'index']);
    Route::post('/vehicles',         [VehicleController::class, 'store']);
    Route::get('/vehicles/{id}',     [VehicleController::class, 'show']);
    Route::put('/vehicles/{id}',     [VehicleController::class, 'update']);
    Route::delete('/vehicles/{id}',  [VehicleController::class, 'destroy']);

    // Services and pricing.
    Route::get('/services',                   [ServiceController::class, 'index']);
    Route::post('/services',                  [ServiceController::class, 'store']);
    Route::put('/services/{id}',              [ServiceController::class, 'update']);
    Route::get('/services/{id}/pricing',      [ServiceController::class, 'getPricing']);
    Route::put('/services/{id}/pricing',      [ServiceController::class, 'updatePricing']);

    // Quick price preview for a set of services.
    Route::get('/pricing/quote-preview', [ServiceController::class, 'quotePreview']);

    // Job orders.
    Route::get('/job-orders',           [JobOrderController::class, 'index']);
    Route::post('/job-orders',          [JobOrderController::class, 'store']);
    Route::get('/job-orders/{id}',      [JobOrderController::class, 'show']);
    Route::put('/job-orders/{id}',      [JobOrderController::class, 'update']);
    Route::post('/job-orders/{id}/cancel', [JobOrderController::class, 'cancel']);

    // Job order items.
    Route::post('/job-orders/{id}/items',              [JobOrderController::class, 'addItem']);
    Route::put('/job-orders/{id}/items/{item_id}',     [JobOrderController::class, 'updateItem']);
    Route::delete('/job-orders/{id}/items/{item_id}',  [JobOrderController::class, 'deleteItem']);

    // Reports.
    Route::get('/reports/daily', [ReportController::class, 'daily']);
});
