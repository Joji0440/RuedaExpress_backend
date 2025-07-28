<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
                'error' => 'Usuario no encontrado'
            ], 401);
        }

        // Verificar si el usuario está activo
        // Asumiendo que tienes un campo 'is_active' en tu tabla users
        if (!$user->is_active ?? true) {
            return response()->json([
                'message' => 'Cuenta desactivada',
                'error' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'
            ], 403);
        }

        return $next($request);
    }
}
