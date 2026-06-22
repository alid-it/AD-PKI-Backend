<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * Beispiel:
     * ->middleware('permission:certificate.revoke')
     * ->middleware('permission:certificate.request,certificate.create')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error_key' => 'backend.auth.unauthenticated',
            ], 401);
        }

        if (empty($permissions)) {
            return response()->json([
                'error_key' => 'backend.auth.no_permission_defined',
            ], 403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'error_key' => 'backend.auth.forbidden',
        ], 403);
    }
}