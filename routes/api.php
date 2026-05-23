<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\OwnerSettingsController;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\Auth\OwnerAuthController;
use App\Http\Controllers\Auth\AdminAuthController;
// Route::get('/test', function () {
//     return response()->json(['message' => 'API working']);
// });
// ─── Public ──────────────────────────────────────────────
Route::post('/auth/telegram',     [App\Http\Controllers\Auth\TelegramAuthController::class, 'login']);
Route::post('/auth/telegram/dev', [App\Http\Controllers\Auth\TelegramAuthController::class, 'loginDev']);
// 🌐 Public Telegram Bot Webhook Listener
Route::post('/telegram/webhook', [\App\Http\Controllers\TelegramBotController::class, 'handleWebhook']);
Route::post('/auth/owner/login',  [App\Http\Controllers\Auth\OwnerAuthController::class, 'login']);
Route::post('/auth/owner/logout', [App\Http\Controllers\Auth\OwnerAuthController::class, 'logout'])->middleware('auth:sanctum');

// Public shop browsing (no auth needed)
Route::get('/shop/{owner}',          [App\Http\Controllers\Customer\ShopController::class, 'show']);
Route::get('/shop/{owner}/products', [App\Http\Controllers\Customer\ProductController::class, 'index']);
Route::get('/shop/{owner}/categories', [App\Http\Controllers\Customer\CategoryController::class, 'index']);

// Route::post('/auth/owner/login', [App\Http\Controllers\Auth\OwnerAuthController::class, 'login']);

// ─── Customer ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::post('/shop/{owner}/checkout',    [App\Http\Controllers\Customer\CheckoutController::class, 'store']);
    Route::get('/orders',                    [App\Http\Controllers\Customer\OrderTrackingController::class, 'index']);
    Route::get('/orders/{order}',            [App\Http\Controllers\Customer\OrderTrackingController::class, 'show']);
    Route::post('/orders/{order}/payment',   [App\Http\Controllers\Customer\OrderTrackingController::class, 'uploadPayment']);

});

// ─── Owner ────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:owner'])->prefix('owner')->group(function () {
    // Categories
    Route::get('/owner/my-link', [App\Http\Controllers\Owner\OrderController::class, 'getMyLink']);
    Route::get('categories',             [App\Http\Controllers\Owner\CategoryController::class, 'index']);
    Route::post('categories',            [App\Http\Controllers\Owner\CategoryController::class, 'store']);
    Route::put('categories/{category}',  [App\Http\Controllers\Owner\CategoryController::class, 'update']);
    Route::delete('categories/{category}', [App\Http\Controllers\Owner\CategoryController::class, 'destroy']);

    // Products
    Route::get('products',              [App\Http\Controllers\Owner\ProductController::class, 'index']);
    Route::post('products',             [App\Http\Controllers\Owner\ProductController::class, 'store']);
    Route::get('products/{product}',    [App\Http\Controllers\Owner\ProductController::class, 'show']);
    Route::put('products/{product}',    [App\Http\Controllers\Owner\ProductController::class, 'update']);
    Route::delete('products/{product}', [App\Http\Controllers\Owner\ProductController::class, 'destroy']);


    //Delivery
    Route::middleware(['auth:sanctum', 'role:delivery'])->prefix('delivery')->group(function () {
        Route::get('tasks', [App\Http\Controllers\Delivery\TaskController::class, 'index']);
        Route::patch('tasks/{order}/delivered', [App\Http\Controllers\Delivery\TaskController::class, 'markDelivered']);
    });
    // Orders
    Route::get('orders',                              [App\Http\Controllers\Owner\OrderController::class, 'index']);
    Route::get('orders/{order}',                      [App\Http\Controllers\Owner\OrderController::class, 'show']);
    Route::patch('orders/{order}/confirm',            [App\Http\Controllers\Owner\OrderController::class, 'confirm']);
    Route::patch('orders/{order}/reject',             [App\Http\Controllers\Owner\OrderController::class, 'reject']);
    Route::patch('orders/{order}/assign-delivery',    [App\Http\Controllers\Owner\OrderController::class, 'assignDelivery']);
    Route::get('delivery-staff',                      [App\Http\Controllers\Owner\OrderController::class, 'deliveryStaff']);

    // Payments
    Route::get('payments', [App\Http\Controllers\Owner\PaymentController::class, 'index']);
});

// ─── Delivery ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:delivery'])->prefix('delivery')->group(function () {
    Route::get('tasks',                         [App\Http\Controllers\Delivery\TaskController::class, 'index']);
    Route::patch('tasks/{delivery}/delivered',  [App\Http\Controllers\Delivery\TaskController::class, 'markDelivered']);
});

// 🚀 ADMIN AREA AUTH HANDSHAKE ROUTES
// This group matches the "api/admin/..." prefix used by your React frontend app!
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/verify-otp', [AdminAuthController::class, 'verifyOtp']);
});

// =================================================================
// 🌟 SHARED ROUTES (Both Super Admin AND Portal Owners can access)
// =================================================================
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {

    // Clean URLs: These will map exactly to /api/admin/owners and /api/admin/dashboard-stats
    Route::get('owners', [\App\Http\Controllers\Admin\OwnerController::class, 'index']);
    Route::post('owners', [\App\Http\Controllers\Admin\OwnerController::class, 'store']);
    Route::get('dashboard-stats', [\App\Http\Controllers\Admin\OwnerController::class, 'dashboardStats']);
    Route::post('/owner/{owner}/register-bot', [\App\Http\Controllers\TelegramBotController::class, 'registerCustomBot']);

    // Toggle status endpoint layout route mapping
    Route::post('owners/{id}/toggle-status', [\App\Http\Controllers\Admin\OwnerController::class, 'toggleStatus']);
});

// =================================================================
// 🛡️ STRICT SUPER ADMIN ONLY ROUTES
// =================================================================
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('owners/{owner}', [\App\Http\Controllers\Admin\OwnerController::class, 'show']);
    Route::put('owners/{owner}', [\App\Http\Controllers\Admin\OwnerController::class, 'update']);
    Route::delete('owners/{owner}', [\App\Http\Controllers\Admin\OwnerController::class, 'destroy']);
    Route::put('owners/{owner}/subscription', [\App\Http\Controllers\Admin\OwnerController::class, 'updateSubscription']);
    Route::get('stats', [\App\Http\Controllers\Admin\SystemMonitorController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'role:owner'])->prefix('owner')->group(function () {


    // Profile
    Route::get('profile',          [App\Http\Controllers\Owner\ProfileController::class, 'show']);
    Route::post('profile',         [App\Http\Controllers\Owner\ProfileController::class, 'update']);
    Route::post('change-password', [App\Http\Controllers\Owner\ProfileController::class, 'changePassword']);
});
