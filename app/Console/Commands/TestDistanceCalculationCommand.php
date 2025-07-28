<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDistanceCalculationCommand extends Command
{
    protected $signature = 'app:test-distance';
    protected $description = 'Probar cálculo de distancia entre mecánico de Ecuador y solicitudes';

    public function handle()
    {
        $this->info('=== PRUEBA DE CÁLCULO DE DISTANCIAS ===');
        $this->newLine();

        // Mecánico de Ecuador
        $mechanic = DB::table('mechanic_profiles as mp')
            ->join('users as u', 'mp.user_id', '=', 'u.id')
            ->where('mp.latitude', '=', -2.12992000)
            ->where('mp.longitude', '=', -79.90804480)
            ->select('mp.*', 'u.name', 'u.email')
            ->first();

        if (!$mechanic) {
            $this->error('❌ No se encontró el mecánico de Ecuador');
            return 1;
        }

        $this->info("👨‍🔧 Mecánico: {$mechanic->name}");
        $this->info("📍 Ubicación: {$mechanic->latitude}, {$mechanic->longitude} (Ecuador)");
        $this->info("🚗 Radio de viaje: {$mechanic->travel_radius} km");
        $this->newLine();

        // Servicios con coordenadas
        $services = DB::table('service_requests')
            ->whereNotNull('location_latitude')
            ->whereNotNull('location_longitude')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $this->info('📋 CÁLCULO DE DISTANCIAS:');
        $this->newLine();

        foreach ($services as $service) {
            // Cálculo manual de distancia usando fórmula de Haversine
            $distance = $this->calculateDistance(
                $mechanic->latitude, 
                $mechanic->longitude,
                $service->location_latitude,
                $service->location_longitude
            );

            $this->line("🔧 Servicio ID: {$service->id} - {$service->title}");
            $this->line("📍 Ubicación: {$service->location_latitude}, {$service->location_longitude}");
            $this->line("📏 Distancia: {$distance} km");
            
            if ($distance <= $mechanic->travel_radius) {
                $this->line("✅ DENTRO del radio de viaje ({$mechanic->travel_radius} km)");
            } else {
                $this->line("❌ FUERA del radio de viaje ({$mechanic->travel_radius} km)");
            }
            $this->newLine();
        }

        return 0;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Radio de la Tierra en kilómetros

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

        $distance = $earthRadius * $c;

        return round($distance, 2);
    }
}
