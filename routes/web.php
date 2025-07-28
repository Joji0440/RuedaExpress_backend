<?php
use Illuminate\Support\Facades\Route;

// Ruta básica para verificar que el servidor está funcionando
Route::get('/', function () {
    return response()->json([
        'message' => 'Mecánica API - Backend funcionando correctamente',
        'version' => '1.0.0',
        'status' => 'active'
    ]);
});

// Todas las demás rutas están en api.php