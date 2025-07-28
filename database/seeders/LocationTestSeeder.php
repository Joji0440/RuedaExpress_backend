<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\MechanicProfile;
use App\Models\ServiceRequest;
use App\Models\Vehicle;

class LocationTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buscar usuarios existentes con rol mecánico
        $mechanicUsers = User::whereHas('roles', function($query) {
            $query->where('name', 'mecanico');
        })->with('mechanicProfile')->get();

        // Coordenadas de ejemplo en diferentes zonas de Ecuador (Manabí)
        $locations = [
            [
                'latitude' => -0.9536,
                'longitude' => -80.7381,
                'address' => 'Manta, Manabí, Ecuador',
            ],
            [
                'latitude' => -1.0582,
                'longitude' => -80.7081,
                'address' => 'Portoviejo, Manabí, Ecuador',
            ],
            [
                'latitude' => -1.3928,
                'longitude' => -80.4564,
                'address' => 'Chone, Manabí, Ecuador',
            ],
        ];

        // Actualizar ubicaciones de mecánicos existentes
        foreach ($mechanicUsers as $index => $user) {
            if ($user->mechanicProfile && isset($locations[$index])) {
                $location = $locations[$index];
                
                $user->mechanicProfile->update([
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'address' => $location['address'],
                    'location_updated_at' => now(),
                    'travel_radius' => rand(5, 20), // Radio entre 5 y 20 km
                ]);

                $this->command->info("Ubicación actualizada para mecánico: {$user->name}");
            }
        }

        // Agregar coordenadas a solicitudes de servicio existentes
        $serviceRequests = ServiceRequest::whereNull('location_latitude')
                                        ->whereNull('location_longitude')
                                        ->whereNotNull('location_address')
                                        ->take(10)
                                        ->get();

        $serviceLocations = [
            [-0.9536, -80.7381], // Manta
            [-1.0582, -80.7081], // Portoviejo
            [-1.3928, -80.4564], // Chone
            [-0.7931, -80.2675], // Bahía de Caráquez
            [-1.5458, -80.4374], // Calceta
            [-0.3515, -80.3936], // Pedernales
            [-1.6756, -80.3624], // El Carmen
            [-1.0367, -80.4581], // Santa Ana
            [-0.6183, -80.2319], // San Vicente
            [-1.2794, -80.8161], // Jipijapa
        ];

        foreach ($serviceRequests as $index => $request) {
            if (isset($serviceLocations[$index])) {
                $coords = $serviceLocations[$index];
                
                $request->update([
                    'location_latitude' => $coords[0],
                    'location_longitude' => $coords[1],
                ]);

                $this->command->info("Coordenadas agregadas a solicitud: {$request->title}");
            }
        }

        $this->command->info('LocationTestSeeder ejecutado exitosamente!');
    }
}
