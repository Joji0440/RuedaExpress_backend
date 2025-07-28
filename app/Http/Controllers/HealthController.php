<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Verificar el estado de salud de la API
     */
    public function check()
    {
        try {
            // Verificar conexión a la base de datos
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Mecánica API funcionando correctamente',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'database' => $dbStatus,
            'environment' => app()->environment()
        ]);
    }
}
