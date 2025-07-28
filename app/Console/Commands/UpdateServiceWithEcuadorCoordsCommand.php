<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateServiceWithEcuadorCoordsCommand extends Command
{
    protected $signature = 'app:update-ecuador-coords';
    protected $description = 'Actualizar solicitudes existentes con coordenadas de Ecuador';

    public function handle()
    {
        $this->info('=== ACTUALIZANDO COORDENADAS A ECUADOR ===');
        $this->newLine();

        // Coordenadas de Guayaquil, Ecuador
        $ecuadorLocations = [
            ['name' => 'Centro de Guayaquil', 'lat' => -2.1894, 'lng' => -79.8837],
            ['name' => 'Malecón 2000', 'lat' => -2.1952, 'lng' => -79.8837],
            ['name' => 'Cerro del Carmen', 'lat' => -2.1736, 'lng' => -79.8968],
            ['name' => 'Urdesa', 'lat' => -2.1567, 'lng' => -79.9037],
            ['name' => 'Kennedy Norte', 'lat' => -2.1243, 'lng' => -79.8980],
        ];

        // Obtener las últimas solicitudes sin coordenadas (las más recientes)
        $services = DB::table('service_requests')
            ->whereNull('location_latitude')
            ->orWhereNull('location_longitude')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'title']);

        if ($services->count() === 0) {
            $this->info('No hay servicios sin coordenadas para actualizar');
            return 0;
        }

        $this->info('📍 Actualizando solicitudes sin coordenadas...');
        $this->newLine();

        $mechanicLat = -2.12992000;
        $mechanicLng = -79.90804480;

        foreach ($services as $index => $service) {
            if ($index >= count($ecuadorLocations)) {
                break;
            }

            $location = $ecuadorLocations[$index];
            
            DB::table('service_requests')
                ->where('id', $service->id)
                ->update([
                    'location_latitude' => $location['lat'],
                    'location_longitude' => $location['lng'],
                    'updated_at' => now(),
                ]);

            // Calcular distancia
            $distance = $this->calculateDistance(
                $mechanicLat, 
                $mechanicLng,
                $location['lat'],
                $location['lng']
            );

            $this->line("✅ ID: {$service->id} | {$service->title}");
            $this->line("   📍 Actualizado a: {$location['name']} ({$location['lat']}, {$location['lng']})");
            $this->line("   📏 Distancia desde mecánico: {$distance} km");
            $this->newLine();
        }

        $this->info('🎯 RESULTADOS:');
        $this->line("✅ {$services->count()} solicitudes actualizadas con coordenadas de Ecuador");
        $this->line('📍 Ubicaciones en Guayaquil asignadas');
        $this->newLine();

        $this->info('🔄 Ejecuta "php artisan app:check-coordinates" para ver los datos actualizados');

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
