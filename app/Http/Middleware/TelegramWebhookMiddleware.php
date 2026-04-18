<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramWebhookMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secret !== config('services.telegram.webhook_secret')) {
            abort(403);
        }
        return $next($request);
    }
}
