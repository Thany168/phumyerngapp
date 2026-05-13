<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Change this check to match how your DB handles the super admin role
        if (!auth()->check() || auth()->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized Access'], 403);
        }

        return $next($request);
    }
}
