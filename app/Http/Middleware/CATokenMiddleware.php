<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CATokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->header('X-CA-Token') !== config('services.ca.token')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}