<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MechanicProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class LocationController extends Controller
{
    /**
     * Actualizar ubicación del usuario
     */
    public function updateLocation(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validatedData = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:20',
            ]);

            // Si no se proporciona dirección, intentar obtenerla mediante geocoding inverso
            if (!isset($validatedData['address']) || !$validatedData['address']) {
                $locationData = $this->reverseGeocode($validatedData['latitude'], $validatedData['longitude']);
                
                if ($locationData) {
                    $validatedData['address'] = $locationData['address'] ?? $user->address;
                    $validatedData['city'] = $locationData['city'] ?? $user->city;
                    $validatedData['state'] = $locationData['state'] ?? $user->state;
                    $validatedData['postal_code'] = $locationData['postal_code'] ?? $user->postal_code;
                }
            }

            $validatedData['location_updated_at'] = now();
            $user->update($validatedData);

            return response()->json([
                'message' => 'Ubicación actualizada exitosamente',
                'data' => [
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'address' => $user->address,
                    'city' => $user->city,
                    'state' => $user->state,
                    'postal_code' => $user->postal_code,
                    'location_updated_at' => $user->location_updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar ubicación',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener ubicación actual del usuario
     */
    public function getCurrentLocation(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            return response()->json([
                'message' => 'Ubicación obtenida exitosamente',
                'data' => [
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'address' => $user->address,
                    'city' => $user->city,
                    'state' => $user->state,
                    'postal_code' => $user->postal_code,
                    'location_updated_at' => $user->location_updated_at,
                    'has_location' => !is_null($user->latitude) && !is_null($user->longitude),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener ubicación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar usuarios cercanos (genérico)
     */
    public function findNearbyUsers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validatedData = $request->validate([
                'radius' => 'nullable|integer|min:1|max:100', // kilómetros
                'role' => 'nullable|string|in:cliente,mecanico,administrador',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $radius = $validatedData['radius'] ?? 25;
            $role = $validatedData['role'] ?? null;
            $limit = $validatedData['limit'] ?? 50;

            $lat = $user->latitude;
            $lng = $user->longitude;

            if (!$lat || !$lng) {
                return response()->json([
                    'message' => 'Debes actualizar tu ubicación primero'
                ], 400);
            }

            $query = User::where('id', '!=', $user->id)
                ->where('is_active', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereRaw(
                    "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?",
                    [$lat, $lng, $lat, $radius]
                )
                ->with('roles:id,name');

            if ($role) {
                $query->role($role);
            }

            $nearbyUsers = $query->limit($limit)
                ->get(['id', 'name', 'email', 'phone', 'address', 'city', 'latitude', 'longitude', 'created_at'])
                ->map(function($nearbyUser) use ($lat, $lng) {
                    $distance = $this->calculateDistance($lat, $lng, $nearbyUser->latitude, $nearbyUser->longitude);
                    $nearbyUser->distance_km = round($distance, 2);
                    return $nearbyUser;
                })
                ->sortBy('distance_km');

            return response()->json([
                'message' => 'Usuarios cercanos encontrados exitosamente',
                'data' => $nearbyUsers->values(),
                'search_params' => [
                    'radius' => $radius,
                    'role' => $role,
                    'your_location' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ]
                ],
                'total_found' => $nearbyUsers->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al buscar usuarios cercanos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular ruta entre dos puntos
     */
    public function calculateRoute(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'destination_latitude' => 'required|numeric|between:-90,90',
                'destination_longitude' => 'required|numeric|between:-180,180',
                'origin_latitude' => 'nullable|numeric|between:-90,90',
                'origin_longitude' => 'nullable|numeric|between:-180,180',
            ]);

            $user = $request->user();
            
            // Usar ubicación del usuario si no se proporciona origen
            $originLat = $validatedData['origin_latitude'] ?? $user->latitude;
            $originLng = $validatedData['origin_longitude'] ?? $user->longitude;

            if (!$originLat || !$originLng) {
                return response()->json([
                    'message' => 'Se requiere ubicación de origen'
                ], 400);
            }

            $destLat = $validatedData['destination_latitude'];
            $destLng = $validatedData['destination_longitude'];

            // Calcular distancia directa
            $directDistance = $this->calculateDistance($originLat, $originLng, $destLat, $destLng);
            
            // Estimar tiempo de viaje (promedio 40 km/h en ciudad, 60 km/h en carretera)
            $estimatedTime = $directDistance <= 10 ? ($directDistance / 40) * 60 : ($directDistance / 60) * 60;

            // Simular información de ruta (en un sistema real se usaría Google Maps API o similar)
            $routeInfo = [
                'origin' => [
                    'latitude' => $originLat,
                    'longitude' => $originLng,
                ],
                'destination' => [
                    'latitude' => $destLat,
                    'longitude' => $destLng,
                ],
                'distance_km' => round($directDistance, 2),
                'estimated_time_minutes' => round($estimatedTime),
                'estimated_cost_fuel' => round($directDistance * 2, 2), // Estimación simple de combustible
                'route_type' => $directDistance <= 10 ? 'urban' : 'highway',
                'waypoints' => [], // En un sistema real, aquí irían los puntos de la ruta
            ];

            return response()->json([
                'message' => 'Ruta calculada exitosamente',
                'data' => $routeInfo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al calcular ruta',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener información del área de servicio de un mecánico
     */
    public function getMechanicServiceArea(Request $request, User $mechanic): JsonResponse
    {
        try {
            if (!$mechanic->hasRole('mecanico')) {
                return response()->json([
                    'message' => 'El usuario especificado no es un mecánico'
                ], 400);
            }

            $mechanicProfile = $mechanic->mechanicProfile;
            if (!$mechanicProfile) {
                return response()->json([
                    'message' => 'El mecánico no tiene perfil configurado'
                ], 400);
            }

            $serviceArea = [
                'mechanic' => [
                    'id' => $mechanic->id,
                    'name' => $mechanic->name,
                    'location' => [
                        'latitude' => $mechanic->latitude,
                        'longitude' => $mechanic->longitude,
                        'address' => $mechanic->address,
                        'city' => $mechanic->city,
                    ],
                ],
                'service_area' => [
                    'center' => [
                        'latitude' => $mechanic->latitude,
                        'longitude' => $mechanic->longitude,
                    ],
                    'radius_km' => $mechanicProfile->travel_radius,
                    'coverage_area' => round(pi() * pow($mechanicProfile->travel_radius, 2), 2), // km²
                ],
                'service_info' => [
                    'specializations' => $mechanicProfile->specializations,
                    'hourly_rate' => $mechanicProfile->hourly_rate,
                    'minimum_service_fee' => $mechanicProfile->minimum_service_fee,
                    'emergency_available' => $mechanicProfile->emergency_available,
                    'is_available' => $mechanicProfile->is_available,
                ],
            ];

            // Agregar distancia si el usuario solicitante tiene ubicación
            $user = $request->user();
            if ($user->latitude && $user->longitude && $mechanic->latitude && $mechanic->longitude) {
                $distance = $this->calculateDistance(
                    $user->latitude, 
                    $user->longitude, 
                    $mechanic->latitude, 
                    $mechanic->longitude
                );
                $serviceArea['distance_from_you'] = round($distance, 2);
                $serviceArea['within_service_area'] = $distance <= $mechanicProfile->travel_radius;
                $serviceArea['estimated_arrival_time'] = round(($distance / 60) * 60); // minutos
            }

            return response()->json([
                'message' => 'Área de servicio obtenida exitosamente',
                'data' => $serviceArea
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener área de servicio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar direcciones (geocoding)
     */
    public function searchAddresses(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'query' => 'required|string|min:3|max:200',
                'limit' => 'nullable|integer|min:1|max:10',
            ]);

            $query = $validatedData['query'];
            $limit = $validatedData['limit'] ?? 5;

            // Simular resultados de búsqueda de direcciones
            // En un sistema real, esto usaría una API como Google Places o OpenStreetMap
            $mockResults = $this->simulateAddressSearch($query, $limit);

            return response()->json([
                'message' => 'Búsqueda de direcciones completada',
                'data' => $mockResults,
                'query' => $query
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al buscar direcciones',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Geocoding inverso - obtener dirección desde coordenadas
     */
    private function reverseGeocode($latitude, $longitude): ?array
    {
        try {
            // En un sistema real, aquí se usaría una API como Google Maps o OpenStreetMap
            // Por ahora, simular una respuesta
            
            // Simulación simple basada en rangos de coordenadas de México
            $cities = [
                'Ciudad de México' => ['lat' => 19.4326, 'lng' => -99.1332, 'state' => 'Ciudad de México'],
                'Guadalajara' => ['lat' => 20.6597, 'lng' => -103.3496, 'state' => 'Jalisco'],
                'Monterrey' => ['lat' => 25.6866, 'lng' => -100.3161, 'state' => 'Nuevo León'],
                'Puebla' => ['lat' => 19.0414, 'lng' => -98.2063, 'state' => 'Puebla'],
                'Tijuana' => ['lat' => 32.5149, 'lng' => -117.0382, 'state' => 'Baja California'],
            ];

            $closestCity = null;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($cities as $cityName => $cityData) {
                $distance = $this->calculateDistance($latitude, $longitude, $cityData['lat'], $cityData['lng']);
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $closestCity = [
                        'city' => $cityName,
                        'state' => $cityData['state'],
                        'distance' => $distance
                    ];
                }
            }

            if ($closestCity && $closestCity['distance'] <= 50) { // Si está dentro de 50km
                return [
                    'address' => "Calle Principal, {$closestCity['city']}",
                    'city' => $closestCity['city'],
                    'state' => $closestCity['state'],
                    'postal_code' => '00000',
                ];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Simular búsqueda de direcciones
     */
    private function simulateAddressSearch($query, $limit): array
    {
        $mockAddresses = [
            [
                'address' => "Av. Insurgentes Sur 1234, Roma Norte, Ciudad de México",
                'city' => 'Ciudad de México',
                'state' => 'Ciudad de México',
                'postal_code' => '06700',
                'latitude' => 19.4112,
                'longitude' => -99.1659,
                'type' => 'street_address'
            ],
            [
                'address' => "Calle López Mateos 567, Centro, Guadalajara",
                'city' => 'Guadalajara',
                'state' => 'Jalisco',
                'postal_code' => '44100',
                'latitude' => 20.6734,
                'longitude' => -103.3444,
                'type' => 'street_address'
            ],
            [
                'address' => "Av. Constitución 890, Centro, Monterrey",
                'city' => 'Monterrey',
                'state' => 'Nuevo León',
                'postal_code' => '64000',
                'latitude' => 25.6693,
                'longitude' => -100.3093,
                'type' => 'street_address'
            ],
        ];

        // Filtrar resultados que coincidan parcialmente con la búsqueda
        $filtered = array_filter($mockAddresses, function($address) use ($query) {
            return stripos($address['address'], $query) !== false || 
                   stripos($address['city'], $query) !== false ||
                   stripos($address['state'], $query) !== false;
        });

        return array_slice($filtered, 0, $limit);
    }

    /**
     * Calcular distancia entre dos puntos geográficos usando la fórmula de Haversine
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
