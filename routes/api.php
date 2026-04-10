<?php

use Illuminate\Support\Facades\Route;

// Public
// Route::post('/auth/telegram', [App\Http\Controllers\Auth\TelegramAuthController::class, 'login']);

// Telegram webhook (no auth — uses secret header instead)
// Route::post('/webhook/telegram', [App\Http\Controllers\Webhook\TelegramWebhookController::class, 'handle'])
//     ->middleware('telegram.webhook');

// Super Admin
// Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')->group(function () {
//     Route::apiResource('owners', App\Http\Controllers\Admin\OwnerController::class);
//     Route::apiResource('subscriptions', App\Http\Controllers\Admin\SubscriptionController::class);
//     Route::get('stats', [App\Http\Controllers\Admin\SystemMonitorController::class, 'index']);
// });

// Owner
// Route::middleware(['auth:sanctum', 'role:owner'])->prefix('owner')->group(function () {
//     Route::apiResource('categories', App\Http\Controllers\Owner\CategoryController::class);
//     Route::apiResource('products', App\Http\Controllers\Owner\ProductController::class);
//     Route::get('orders', [App\Http\Controllers\Owner\OrderController::class, 'index']);
//     Route::patch('orders/{order}/confirm', [App\Http\Controllers\Owner\OrderController::class, 'confirm']);
//     Route::patch('orders/{order}/reject', [App\Http\Controllers\Owner\OrderController::class, 'reject']);
//     Route::patch('orders/{order}/assign-delivery', [App\Http\Controllers\Owner\DeliveryController::class, 'assign']);
// });

// Customer (authenticated via Telegram init data)
// Route::middleware(['auth:sanctum', 'role:customer'])->prefix('shop/{owner}')->group(function () {
//     Route::get('products', [App\Http\Controllers\Customer\ProductController::class, 'index']);
//     Route::post('orders', [App\Http\Controllers\Customer\CheckoutController::class, 'store']);
//     Route::get('orders/{order}/track', [App\Http\Controllers\Customer\OrderTrackingController::class, 'show']);
// });

// Delivery
// Route::middleware(['auth:sanctum', 'role:delivery'])->prefix('delivery')->group(function () {
//     Route::get('tasks', [App\Http\Controllers\Delivery\TaskController::class, 'index']);
//     Route::patch('tasks/{order}/delivered', [App\Http\Controllers\Delivery\TaskController::class, 'markDelivered']);
// });
