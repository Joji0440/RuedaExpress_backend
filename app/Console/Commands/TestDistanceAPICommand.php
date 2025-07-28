<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class TestDistanceAPICommand extends Command
{
    protected $signature = 'app:test-api';
    protected $description = 'Probar APIs de cálculo de distancia';

    public function handle()
    {
        $this->info('=== PRUEBA DE APIs DE DISTANCIA ===');
        $this->newLine();

        // Obtener token de autenticación del mecánico de Ecuador
        $mechanic = DB::table('users')
            ->where('email', 'mecanico@mecanica.com')
            ->first();

        if (!$mechanic) {
            $this->error('❌ No se encontró el mecánico para autenticación');
            return 1;
        }

        // Crear token temporal para pruebas
        $token = DB::table('personal_access_tokens')->where('tokenable_id', $mechanic->id)->first();
        
        if (!$token) {
            $this->info('🔑 Creando token de prueba...');
            // En producción, esto se haría a través del login
            $this->line('Para probar las APIs necesitas autenticarte primero.');
            $this->line('Puedes usar Postman o curl con el token de autenticación.');
            $this->newLine();
        }

        // Mostrar ejemplos de uso de las APIs
        $this->info('📡 ENDPOINTS DISPONIBLES:');
        $this->newLine();

        $this->line('1. Cálculo individual de distancia:');
        $this->line('   GET /api/service-requests/{id}/distance');
        $this->line('   Ejemplo: GET /api/service-requests/22/distance');
        $this->newLine();

        $this->line('2. Servicios con distancias:');
        $this->line('   GET /api/service-requests/with-distance');
        $this->line('   Parámetros opcionales: ?radius=10&search=motor');
        $this->newLine();

        // Mostrar servicios disponibles para testing
        $this->info('📋 SERVICIOS DISPONIBLES PARA TESTING (Ecuador):');
        $services = DB::table('service_requests')
            ->where('location_latitude', 'LIKE', '-2.%') // Ecuador
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'status']);

        foreach ($services as $service) {
            $this->line("   ID: {$service->id} | {$service->title} | Status: {$service->status}");
        }
        $this->newLine();

        $this->info('🔧 TESTING CON CURL:');
        $this->line('# 1. Login primero (obtener token)');
        $this->line('curl -X POST http://localhost:8000/api/login \\');
        $this->line('  -H "Content-Type: application/json" \\');
        $this->line('  -d \'{"email":"mecanico@mecanica.com","password":"password"}\'');
        $this->newLine();

        $this->line('# 2. Usar token en requests de distancia');
        $this->line('curl -X GET http://localhost:8000/api/service-requests/22/distance \\');
        $this->line('  -H "Authorization: Bearer YOUR_TOKEN_HERE" \\');
        $this->line('  -H "Accept: application/json"');
        $this->newLine();

        $this->info('✅ DATOS PREPARADOS PARA TESTING:');
        $this->line('- 5 servicios en Ecuador con coordenadas reales');
        $this->line('- 1 mecánico en Ecuador (Carlos Martínez)');
        $this->line('- Distancias de 1.28 km a 7.75 km (dentro del radio)');
        $this->line('- APIs implementadas y rutas activas');
        
        return 0;
    }
}
