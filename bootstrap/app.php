<?php

use App\Http\Controllers\Webhook\TelegramWebhookController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsSuperAdmin;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\TelegramWebhookMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
        'api/telegram/webhook/*'
        ]);
        $middleware->alias([
        'is_super_admin' => \App\Http\Middleware\IsSuperAdmin::class,
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
