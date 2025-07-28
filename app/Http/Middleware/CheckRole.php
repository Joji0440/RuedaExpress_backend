<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Asegurar que se use el guard correcto para API
        $user = Auth::guard('sanctum')->user() ?? Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.'
            ], 401);
        }

        if (!$user->hasAnyRole($roles)) {
            return response()->json([
                'message' => 'No tienes permiso para acceder a este recurso. Roles requeridos: ' . implode(', ', $roles)
            ], 403);
        }

        return $next($request);
    }
}
