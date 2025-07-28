<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckCoordinatesCommand extends Command
{
    protected $signature = 'app:check-coordinates';
    protected $description = 'Verificar datos de coordenadas en la base de datos';

    public function handle()
    {
        $this->info('=== VERIFICACIÓN DE DATOS DE COORDENADAS ===');
        $this->newLine();

        // 1. Verificar solicitudes de servicio con coordenadas
        $this->info('🔍 1. SOLICITUDES DE SERVICIO CON COORDENADAS:');
        $services = DB::table('service_requests')
            ->select('id', 'title', 'status', 'location_latitude', 'location_longitude', 'created_at')
            ->whereNotNull('location_latitude')
            ->whereNotNull('location_longitude')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($services->count() > 0) {
            foreach ($services as $service) {
                $this->line("ID: {$service->id} | {$service->title} | Status: {$service->status}");
                $this->line("   📍 Coordenadas: {$service->location_latitude}, {$service->location_longitude}");
                $this->line("   📅 Creado: {$service->created_at}");
                $this->newLine();
            }
        } else {
            $this->error('❌ No hay solicitudes de servicio con coordenadas');
        }

        // 2. Total sin coordenadas
        $withoutCoords = DB::table('service_requests')
            ->where(function($query) {
                $query->whereNull('location_latitude')
                      ->orWhereNull('location_longitude');
            })
            ->count();
        
        $totalServices = DB::table('service_requests')->count();
        $this->info("📊 Total de servicios: {$totalServices}");
        $this->info("📊 Sin coordenadas: {$withoutCoords}");
        $this->info("📊 Con coordenadas: " . ($totalServices - $withoutCoords));
        $this->newLine();

        // 3. Verificar mecánicos con coordenadas
        $this->info('🔍 2. MECÁNICOS CON COORDENADAS:');
        $mechanics = DB::table('mechanic_profiles as mp')
            ->join('users as u', 'mp.user_id', '=', 'u.id')
            ->select('mp.latitude', 'mp.longitude', 'mp.travel_radius', 'mp.is_available', 'u.name', 'u.email')
            ->whereNotNull('mp.latitude')
            ->whereNotNull('mp.longitude')
            ->get();

        if ($mechanics->count() > 0) {
            foreach ($mechanics as $mechanic) {
                $this->line("👨‍🔧 {$mechanic->name} ({$mechanic->email})");
                $this->line("   📍 Coordenadas: {$mechanic->latitude}, {$mechanic->longitude}");
                $this->line("   🚗 Radio de viaje: {$mechanic->travel_radius} km");
                $this->line("   🟢 Disponible: " . ($mechanic->is_available ? 'Sí' : 'No'));
                $this->newLine();
            }
        } else {
            $this->error('❌ No hay mecánicos con coordenadas configuradas');
        }

        // 4. Total de mecánicos
        $totalMechanics = DB::table('mechanic_profiles')->count();
        $mechanicsWithCoords = DB::table('mechanic_profiles')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();
        
        $this->info("📊 Total de mecánicos: {$totalMechanics}");
        $this->info("📊 Con coordenadas: {$mechanicsWithCoords}");
        $this->info("📊 Sin coordenadas: " . ($totalMechanics - $mechanicsWithCoords));
        $this->newLine();

        // 5. Últimas solicitudes creadas (con o sin coordenadas)
        $this->info('🔍 3. ÚLTIMAS 5 SOLICITUDES CREADAS:');
        $recentServices = DB::table('service_requests')
            ->select('id', 'title', 'status', 'location_latitude', 'location_longitude', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentServices as $service) {
            $hasCoords = !is_null($service->location_latitude) && !is_null($service->location_longitude);
            $coordsStatus = $hasCoords ? '✅ Con coordenadas' : '❌ Sin coordenadas';
            
            $this->line("ID: {$service->id} | {$service->title} | {$coordsStatus}");
            if ($hasCoords) {
                $this->line("   📍 Coordenadas: {$service->location_latitude}, {$service->location_longitude}");
            }
            $this->line("   📅 Creado: {$service->created_at}");
            $this->newLine();
        }

        return 0;
    }
}
