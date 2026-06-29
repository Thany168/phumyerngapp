<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\OwnerSettingsController;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\Auth\OwnerAuthController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Owner\ProductController;
// ─── PUBLIC AUTH & TELEGRAM WEBHOOK ───────────────────────────────────────
Route::post('/auth/telegram',     [App\Http\Controllers\Auth\TelegramAuthController::class, 'login']);
Route::post('/auth/telegram/dev', [App\Http\Controllers\Auth\TelegramAuthController::class, 'loginDev']);
Route::post('/telegram/webhook',  [\App\Http\Controllers\TelegramBotController::class, 'handleWebhook']);
Route::post('/auth/owner/login',  [App\Http\Controllers\Auth\OwnerAuthController::class, 'login']);
Route::post('/auth/owner/logout', [App\Http\Controllers\Auth\OwnerAuthController::class, 'logout'])->middleware('auth:sanctum');

// ─── PUBLIC SHOP ACCESS (GUEST FRIENDLY) ──────────────────────────────────
Route::get('/shop/{owner}',            [App\Http\Controllers\Customer\ShopController::class, 'show']);
Route::get('/shop/{owner}/products',   [App\Http\Controllers\Customer\ProductController::class, 'index']);
Route::get('/shop/{owner}/categories', [App\Http\Controllers\Customer\CategoryController::class, 'index']);
Route::post('/shop/{owner}/checkout',  [App\Http\Controllers\Customer\CheckoutController::class, 'store']);

// 🎯 FIXED: Moved out of the auth group to prevent the 403 error during your automated frontend redirect flow!
Route::post('/orders/{order}/payment', [App\Http\Controllers\Customer\OrderTrackingController::class, 'uploadPayment']);
Route::get('/media', [MediaController::class, 'stream']); // New media streaming endpoint

// ─── CUSTOMER TRACKING (AUTHENTICATED ONLY) ────────────────────────────────
Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::get('/orders',         [App\Http\Controllers\Customer\OrderTrackingController::class, 'index']);
    Route::get('/orders/{order}', [App\Http\Controllers\Customer\OrderTrackingController::class, 'show']);
});


// ─── PORTAL OWNER MANAGEMENT AREA ──────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:owner'])->prefix('owner')->group(function () {
    // Categories
    Route::get('/my-link', [App\Http\Controllers\Owner\OrderController::class, 'getMyLink']);

    Route::get('categories',               [App\Http\Controllers\Owner\CategoryController::class, 'index']);
    Route::post('categories',              [App\Http\Controllers\Owner\CategoryController::class, 'store']);
    Route::put('categories/{category}',    [App\Http\Controllers\Owner\CategoryController::class, 'update']);
    Route::delete('categories/{category}', [App\Http\Controllers\Owner\CategoryController::class, 'destroy']);

    // Products Group Mappings
    Route::get('products',              [App\Http\Controllers\Owner\ProductController::class, 'index']);
    Route::post('products',             [App\Http\Controllers\Owner\ProductController::class, 'store']);
    Route::get('products/{product}',    [App\Http\Controllers\Owner\ProductController::class, 'show']);
    Route::delete('products/{product}', [App\Http\Controllers\Owner\ProductController::class, 'destroy']);

    // 🎯 THE ROUTING FIX: Register BOTH routes here so Laravel handles the internal conversion smoothly!
    Route::post('products/{product}',   [App\Http\Controllers\Owner\ProductController::class, 'update']);
    Route::put('products/{product}',    [App\Http\Controllers\Owner\ProductController::class, 'update']);

    // Orders
    Route::get('orders',                           [App\Http\Controllers\Owner\OrderController::class, 'index']);
    Route::get('orders/{order}',                   [App\Http\Controllers\Owner\OrderController::class, 'show']);
    Route::patch('orders/{order}/confirm',         [App\Http\Controllers\Owner\OrderController::class, 'confirm']);
    Route::patch('orders/{order}/reject',          [App\Http\Controllers\Owner\OrderController::class, 'reject']);
    Route::patch('orders/{order}/assign-delivery', [App\Http\Controllers\Owner\OrderController::class, 'assignDelivery']);
    Route::get('delivery-staff',                   [App\Http\Controllers\Owner\OrderController::class, 'deliveryStaff']);

    // Payments & Profile
    Route::get('payments',         [App\Http\Controllers\Owner\PaymentController::class, 'index']);
    Route::get('profile',          [App\Http\Controllers\Owner\ProfileController::class, 'show']);
    Route::post('profile',         [App\Http\Controllers\Owner\ProfileController::class, 'update']);
    Route::post('change-password', [App\Http\Controllers\Owner\ProfileController::class, 'changePassword']);
});


// ─── DELIVERY STAFF MANAGEMENT AREA ────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:delivery'])->prefix('delivery')->group(function () {
    Route::get('tasks',                        [App\Http\Controllers\Delivery\TaskController::class, 'index']);
    Route::patch('tasks/{delivery}/delivered', [App\Http\Controllers\Delivery\TaskController::class, 'markDelivered']);
});


// ─── SUPER ADMIN & SYSTEM OPERATIONS ───────────────────────────────────────
Route::prefix('admin')->group(function () {
    Route::post('/login',      [AdminAuthController::class, 'login']);
    Route::post('/verify-otp', [AdminAuthController::class, 'verifyOtp']);
});

// Shared Admin Portal Routes
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::get('owners',                     [\App\Http\Controllers\Admin\OwnerController::class, 'index']);
    Route::post('owners',                    [\App\Http\Controllers\Admin\OwnerController::class, 'store']);
    Route::get('dashboard-stats',            [\App\Http\Controllers\Admin\OwnerController::class, 'dashboardStats']);
    Route::post('/owner/{owner}/register-bot', [\App\Http\Controllers\TelegramBotController::class, 'registerCustomBot']);
    Route::post('owners/{id}/toggle-status', [\App\Http\Controllers\Admin\OwnerController::class, 'toggleStatus']);
});

// Strict Super Admin Access Guards
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('owners/{owner}',              [\App\Http\Controllers\Admin\OwnerController::class, 'show']);
    Route::put('owners/{owner}',              [\App\Http\Controllers\Admin\OwnerController::class, 'update']);
    Route::delete('owners/{owner}',           [\App\Http\Controllers\Admin\OwnerController::class, 'destroy']);
    Route::put('owners/{owner}/subscription', [\App\Http\Controllers\Admin\OwnerController::class, 'updateSubscription']);
    Route::get('stats',                       [\App\Http\Controllers\Admin\SystemMonitorController::class, 'index']);
});
