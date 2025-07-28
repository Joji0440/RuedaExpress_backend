<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\MechanicProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    /**
     * Crear una nueva solicitud de servicio
     */
    public function createServiceRequest(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo clientes pueden crear solicitudes de servicio
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Solo los clientes pueden crear solicitudes de servicio'
                ], 403);
            }

            // Verificar que el usuario esté activo
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Tu cuenta está desactivada. No puedes crear solicitudes de servicio'
                ], 403);
            }

            $validatedData = $request->validate([
                'vehicle_id' => 'required|integer|exists:vehicles,id',
                'mechanic_id' => 'nullable|integer|exists:users,id',
                'service_type' => 'required|string|in:emergency,scheduled,diagnostic,maintenance,repair',
                'problem_description' => 'required|string|max:1000',
                'location_description' => 'required|string|max:500',
                'preferred_date' => 'nullable|date|after:now',
                'preferred_time' => 'nullable|string',
                'max_budget' => 'nullable|numeric|min:0',
                'priority' => 'required|string|in:low,normal,high,emergency',
                'images' => 'nullable|array|max:5',
                'images.*' => 'string', // Base64 images
            ]);

            // Verificar que el vehículo pertenece al cliente
            $vehicle = Vehicle::findOrFail($validatedData['vehicle_id']);
            if ($vehicle->user_id !== $user->id) {
                return response()->json([
                    'message' => 'El vehículo seleccionado no te pertenece'
                ], 403);
            }

            // Verificar que el mecánico existe y está disponible (si se especifica)
            if (isset($validatedData['mechanic_id'])) {
                $mechanic = User::findOrFail($validatedData['mechanic_id']);
                if (!$mechanic->hasRole('mecanico')) {
                    return response()->json([
                        'message' => 'El usuario seleccionado no es un mecánico'
                    ], 400);
                }

                $mechanicProfile = $mechanic->mechanicProfile;
                if (!$mechanicProfile || !$mechanicProfile->is_available) {
                    return response()->json([
                        'message' => 'El mecánico seleccionado no está disponible'
                    ], 400);
                }
            }

            // Crear solicitud de servicio (usando tabla separada en el futuro)
            // Por ahora, almacenar en una estructura JSON temporal
            $serviceRequest = [
                'id' => uniqid('service_', true),
                'client_id' => $user->id,
                'vehicle_id' => $validatedData['vehicle_id'],
                'mechanic_id' => $validatedData['mechanic_id'] ?? null,
                'service_type' => $validatedData['service_type'],
                'problem_description' => $validatedData['problem_description'],
                'location_description' => $validatedData['location_description'],
                'client_location' => [
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'address' => $user->address,
                    'city' => $user->city,
                ],
                'preferred_date' => $validatedData['preferred_date'] ?? null,
                'preferred_time' => $validatedData['preferred_time'] ?? null,
                'max_budget' => $validatedData['max_budget'] ?? null,
                'priority' => $validatedData['priority'],
                'images' => $validatedData['images'] ?? [],
                'status' => 'pending',
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            // En un sistema completo, esto se guardaría en una tabla 'service_requests'
            // Por ahora, devolvemos la estructura simulada
            return response()->json([
                'message' => 'Solicitud de servicio creada exitosamente',
                'data' => $serviceRequest
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear solicitud de servicio',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener solicitudes de servicio disponibles (para mecánicos)
     */
    public function getAvailableRequests(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo mecánicos pueden ver solicitudes disponibles
            if (!$user->hasRole('mecanico')) {
                return response()->json([
                    'message' => 'Solo los mecánicos pueden ver solicitudes disponibles'
                ], 403);
            }

            // Verificar que el usuario esté activo
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Tu cuenta está desactivada. No puedes ver solicitudes disponibles'
                ], 403);
            }

            $mechanicProfile = $user->mechanicProfile;
            if (!$mechanicProfile || !$mechanicProfile->is_available) {
                return response()->json([
                    'message' => 'Debes completar tu perfil y estar disponible para ver solicitudes'
                ], 400);
            }

            // Simular solicitudes disponibles cerca del mecánico
            $lat = $user->latitude;
            $lng = $user->longitude;
            $radius = $mechanicProfile->travel_radius;

            if (!$lat || !$lng) {
                return response()->json([
                    'message' => 'Debes actualizar tu ubicación para ver solicitudes'
                ], 400);
                }

            // En un sistema completo, esto vendría de una tabla 'service_requests'
            // Por ahora, simulamos algunas solicitudes
            $mockRequests = $this->generateMockServiceRequests($lat, $lng, $radius);

            return response()->json([
                'message' => 'Solicitudes disponibles obtenidas exitosamente',
                'data' => $mockRequests,
                'mechanic_info' => [
                    'location' => ['latitude' => $lat, 'longitude' => $lng],
                    'travel_radius' => $radius,
                    'specializations' => $mechanicProfile->specializations,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener solicitudes disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Responder a una solicitud de servicio
     */
    public function respondToRequest(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo mecánicos pueden responder a solicitudes
            if (!$user->hasRole('mecanico')) {
                return response()->json([
                    'message' => 'Solo los mecánicos pueden responder a solicitudes'
                ], 403);
            }

            // Verificar que el usuario esté activo
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Tu cuenta está desactivada. No puedes responder a solicitudes de servicio'
                ], 403);
            }

            $validatedData = $request->validate([
                'request_id' => 'required|string',
                'response_type' => 'required|string|in:accept,quote,decline',
                'estimated_cost' => 'nullable|numeric|min:0',
                'estimated_duration' => 'nullable|integer|min:1|max:480', // minutos
                'arrival_time' => 'nullable|date|after:now',
                'message' => 'nullable|string|max:500',
                'required_tools' => 'nullable|array',
                'required_parts' => 'nullable|array',
            ]);

            // Simular respuesta a solicitud
            $response = [
                'id' => uniqid('response_', true),
                'service_request_id' => $validatedData['request_id'],
                'mechanic_id' => $user->id,
                'mechanic_name' => $user->name,
                'mechanic_profile' => $user->mechanicProfile,
                'response_type' => $validatedData['response_type'],
                'estimated_cost' => $validatedData['estimated_cost'],
                'estimated_duration' => $validatedData['estimated_duration'],
                'arrival_time' => $validatedData['arrival_time'],
                'message' => $validatedData['message'],
                'required_tools' => $validatedData['required_tools'] ?? [],
                'required_parts' => $validatedData['required_parts'] ?? [],
                'status' => 'sent',
                'created_at' => now()->toISOString(),
            ];

            return response()->json([
                'message' => 'Respuesta enviada exitosamente',
                'data' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al enviar respuesta',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener historial de servicios
     */
    public function getServiceHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $history = [];

            if ($user->hasRole('cliente')) {
                // Para clientes: historial de sus solicitudes
                $vehicles = $user->vehicles()->with('user:id,name')->get();
                
                foreach ($vehicles as $vehicle) {
                    if ($vehicle->service_history) {
                        foreach ($vehicle->service_history as $record) {
                            $history[] = [
                                'id' => uniqid('history_', true),
                                'vehicle' => $vehicle->make . ' ' . $vehicle->model,
                                'service' => $record['service'] ?? 'Servicio',
                                'description' => $record['description'] ?? '',
                                'cost' => $record['cost'] ?? 0,
                                'date' => $record['date'] ?? now()->toDateString(),
                                'mechanic_name' => $record['mechanic_name'] ?? 'No especificado',
                                'status' => 'completed',
                                'type' => 'vehicle_service'
                            ];
                        }
                    }
                }
            } elseif ($user->hasRole('mecanico')) {
                // Para mecánicos: servicios que han realizado
                $mechanicProfile = $user->mechanicProfile;
                if ($mechanicProfile) {
                    // Simular historial de trabajos
                    $history = $this->generateMockMechanicHistory($user->id, $mechanicProfile->total_jobs);
                }
            }

            return response()->json([
                'message' => 'Historial de servicios obtenido exitosamente',
                'data' => collect($history)->sortByDesc('date')->values()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener historial de servicios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar mecánicos por especialidad
     */
    public function findMechanicsBySpecialty(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo clientes pueden buscar mecánicos
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Solo los clientes pueden buscar mecánicos'
                ], 403);
            }

            $validatedData = $request->validate([
                'specialization' => 'required|string|in:motor,transmision,frenos,suspension,electrico,aire_acondicionado,diagnostico,carroceria,llantas,otros',
                'radius' => 'nullable|integer|min:1|max:100',
                'max_rate' => 'nullable|numeric|min:0',
                'min_rating' => 'nullable|numeric|min:0|max:5',
                'emergency_only' => 'nullable|boolean',
                'verified_only' => 'nullable|boolean',
            ]);

            $radius = $validatedData['radius'] ?? 25;
            $lat = $user->latitude;
            $lng = $user->longitude;

            if (!$lat || !$lng) {
                return response()->json([
                    'message' => 'Debes actualizar tu ubicación para buscar mecánicos'
                ], 400);
            }

            $query = MechanicProfile::with(['user:id,name,email,phone,address,city,latitude,longitude'])
                ->whereJsonContains('specializations', $validatedData['specialization'])
                ->where('is_available', true)
                ->whereHas('user', function($q) use ($lat, $lng, $radius) {
                    $q->where('is_active', true)
                      ->whereRaw(
                          "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?",
                          [$lat, $lng, $lat, $radius]
                      );
                });

            // Aplicar filtros adicionales
            if (isset($validatedData['max_rate'])) {
                $query->where('hourly_rate', '<=', $validatedData['max_rate']);
            }

            if (isset($validatedData['min_rating'])) {
                $query->where('rating_average', '>=', $validatedData['min_rating']);
            }

            if (isset($validatedData['emergency_only']) && $validatedData['emergency_only']) {
                $query->where('emergency_available', true);
            }

            if (isset($validatedData['verified_only']) && $validatedData['verified_only']) {
                $query->where('is_verified', true);
            }

            $mechanics = $query->get()
                ->map(function($mechanic) use ($lat, $lng) {
                    $mechanicLat = $mechanic->user->latitude;
                    $mechanicLng = $mechanic->user->longitude;
                    
                    if ($mechanicLat && $mechanicLng) {
                        $distance = $this->calculateDistance($lat, $lng, $mechanicLat, $mechanicLng);
                        $mechanic->distance_km = round($distance, 2);
                        $mechanic->estimated_arrival_minutes = round(($distance / 60) * 60);
                    }
                    
                    return $mechanic;
                })
                ->sortBy('distance_km');

            return response()->json([
                'message' => 'Mecánicos encontrados exitosamente',
                'data' => $mechanics->values(),
                'search_params' => $validatedData,
                'results_count' => $mechanics->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al buscar mecánicos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar solicitudes de servicio simuladas
     */
    private function generateMockServiceRequests($lat, $lng, $radius): array
    {
        $requests = [];
        $serviceTypes = ['emergency', 'scheduled', 'diagnostic', 'maintenance', 'repair'];
        $priorities = ['low', 'normal', 'high', 'emergency'];
        
        for ($i = 0; $i < 5; $i++) {
            // Generar coordenadas aleatorias dentro del radio
            $randomLat = $lat + (mt_rand(-100, 100) / 10000) * ($radius / 10);
            $randomLng = $lng + (mt_rand(-100, 100) / 10000) * ($radius / 10);
            $distance = $this->calculateDistance($lat, $lng, $randomLat, $randomLng);
            
            if ($distance <= $radius) {
                $requests[] = [
                    'id' => 'req_' . uniqid(),
                    'client_name' => 'Cliente ' . ($i + 1),
                    'vehicle' => 'Toyota Corolla 2019',
                    'service_type' => $serviceTypes[array_rand($serviceTypes)],
                    'problem_description' => 'Mi vehículo presenta problemas con ' . ['el motor', 'los frenos', 'la transmisión', 'el sistema eléctrico'][array_rand(['el motor', 'los frenos', 'la transmisión', 'el sistema eléctrico'])],
                    'location' => [
                        'latitude' => $randomLat,
                        'longitude' => $randomLng,
                        'address' => 'Dirección de prueba ' . ($i + 1),
                        'description' => 'Descripción de la ubicación'
                    ],
                    'distance_km' => round($distance, 2),
                    'priority' => $priorities[array_rand($priorities)],
                    'max_budget' => mt_rand(50, 300),
                    'preferred_time' => now()->addHours(mt_rand(1, 24))->format('Y-m-d H:i:s'),
                    'created_at' => now()->subMinutes(mt_rand(5, 120))->toISOString(),
                ];
            }
        }
        
        return $requests;
    }

    /**
     * Generar historial simulado para mecánico
     */
    private function generateMockMechanicHistory($mechanicId, $totalJobs): array
    {
        $history = [];
        $services = ['Cambio de aceite', 'Revisión de frenos', 'Diagnóstico computarizado', 'Cambio de batería', 'Afinación mayor'];
        
        for ($i = 0; $i < min($totalJobs, 10); $i++) {
            $history[] = [
                'id' => 'job_' . uniqid(),
                'client_name' => 'Cliente ' . mt_rand(1, 100),
                'vehicle' => ['Honda Civic', 'Toyota Corolla', 'Nissan Sentra', 'Ford Focus'][array_rand(['Honda Civic', 'Toyota Corolla', 'Nissan Sentra', 'Ford Focus'])],
                'service' => $services[array_rand($services)],
                'cost' => mt_rand(50, 200),
                'duration' => mt_rand(60, 240) . ' minutos',
                'rating' => mt_rand(40, 50) / 10,
                'date' => now()->subDays(mt_rand(1, 365))->toDateString(),
                'status' => 'completed',
                'type' => 'mechanic_service'
            ];
        }
        
        return $history;
    }

    /**
     * Calcular distancia entre dos puntos geográficos
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371; // Radio de la Tierra en kilómetros

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}
