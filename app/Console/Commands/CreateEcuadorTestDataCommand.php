<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateEcuadorTestDataCommand extends Command
{
    protected $signature = 'app:create-ecuador-data';
    protected $description = 'Crear datos de prueba con coordenadas de Ecuador';

    public function handle()
    {
        $this->info('=== CREANDO DATOS DE PRUEBA PARA ECUADOR ===');
        $this->newLine();

        // Coordenadas de diferentes sectores de Guayaquil, Ecuador
        $ecuadorLocations = [
            ['name' => 'Centro de Guayaquil', 'lat' => -2.1894, 'lng' => -79.8837],
            ['name' => 'Malecón 2000', 'lat' => -2.1952, 'lng' => -79.8837],
            ['name' => 'Cerro del Carmen', 'lat' => -2.1736, 'lng' => -79.8968],
            ['name' => 'Urdesa', 'lat' => -2.1567, 'lng' => -79.9037],
            ['name' => 'Kennedy Norte', 'lat' => -2.1243, 'lng' => -79.8980],
            ['name' => 'Via a la Costa', 'lat' => -2.1451, 'lng' => -79.9684],
            ['name' => 'Samborondón', 'lat' => -2.1056, 'lng' => -79.8944],
        ];

        $services = [
            'Cambio de aceite urgente',
            'Revisión de frenos',
            'Diagnóstico de motor',
            'Cambio de batería',
            'Reparación de transmisión',
            'Mantenimiento preventivo',
            'Emergencia - No enciende',
        ];

        $this->info('📍 Creando solicitudes de servicio en Ecuador...');
        $this->newLine();

        for ($i = 0; $i < 7; $i++) {
            $location = $ecuadorLocations[$i];
            $serviceTitle = $services[$i];
            
            $serviceId = DB::table('service_requests')->insertGetId([
                'client_id' => 13, // Juan Pérez (cliente@mecanica.com)
                'title' => $serviceTitle,
                'description' => "Servicio en {$location['name']}, Guayaquil, Ecuador. Datos de prueba para testing de distancias.",
                'service_type' => ['maintenance', 'emergency', 'inspection'][rand(0, 2)],
                'urgency_level' => ['low', 'medium', 'high'][rand(0, 2)],
                'status' => 'pending',
                'location_latitude' => $location['lat'],
                'location_longitude' => $location['lng'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Calcular distancia desde el mecánico de Ecuador
            $mechanicLat = -2.12992000;
            $mechanicLng = -79.90804480;
            
            $distance = $this->calculateDistance(
                $mechanicLat, 
                $mechanicLng,
                $location['lat'],
                $location['lng']
            );

            $this->line("✅ ID: {$serviceId} | {$serviceTitle}");
            $this->line("   📍 {$location['name']}: {$location['lat']}, {$location['lng']}");
            $this->line("   📏 Distancia desde mecánico: {$distance} km");
            $this->newLine();
        }

        $this->info('🎯 RESULTADOS:');
        $this->line('✅ 7 solicitudes de servicio creadas en Ecuador');
        $this->line('📍 Ubicaciones distribuidas en Guayaquil');
        $this->line('📏 Distancias calculadas desde mecánico actual');
        $this->newLine();

        $this->info('🔄 Ejecuta "php artisan app:check-coordinates" para ver los nuevos datos');

        return 0;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;

        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
