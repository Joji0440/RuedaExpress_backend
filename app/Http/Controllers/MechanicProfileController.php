<?php

namespace App\Http\Controllers;

use App\Models\MechanicProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MechanicProfileController extends Controller
{
    /**
     * Obtener perfiles de mecánicos disponibles
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $query = MechanicProfile::with(['user:id,name,email,phone,address,city,state,latitude,longitude,is_active']);
            
            // Filtros
            if ($request->has('specialization')) {
                $specialization = $request->specialization;
                $query->whereJsonContains('specializations', $specialization);
            }
            
            if ($request->has('available_now')) {
                $query->where('is_available', true);
            }
            
            if ($request->has('verified_only')) {
                $query->where('is_verified', true);
            }
            
            if ($request->has('emergency_available')) {
                $query->where('emergency_available', true);
            }
            
            if ($request->has('max_rate')) {
                $query->where('hourly_rate', '<=', $request->max_rate);
            }
            
            if ($request->has('min_rating')) {
                $query->where('rating_average', '>=', $request->min_rating);
            }

            // Filtro por ubicación para clientes
            if ($request->has('location') && $user->hasRole('cliente')) {
                $radius = $request->get('radius', 50); // 50km por defecto
                $lat = $user->latitude;
                $lng = $user->longitude;
                
                if ($lat && $lng) {
                    $query->whereHas('user', function($q) use ($lat, $lng, $radius) {
                        $q->whereRaw(
                            "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?",
                            [$lat, $lng, $lat, $radius]
                        );
                    });
                }
            }
            
            // Solo mostrar perfiles activos
            $query->whereHas('user', function($q) {
                $q->where('is_active', true);
            });
            
            $profiles = $query->orderBy('rating_average', 'desc')
                             ->orderBy('total_jobs', 'desc')
                             ->get();

            // Agregar distancia para clientes
            if ($user->hasRole('cliente') && $user->latitude && $user->longitude) {
                $profiles = $profiles->map(function($profile) use ($user) {
                    $mechanicLat = $profile->user->latitude;
                    $mechanicLng = $profile->user->longitude;
                    
                    if ($mechanicLat && $mechanicLng) {
                        $distance = $this->calculateDistance(
                            $user->latitude, 
                            $user->longitude, 
                            $mechanicLat, 
                            $mechanicLng
                        );
                        $profile->distance_km = round($distance, 2);
                    }
                    
                    return $profile;
                })->sortBy('distance_km');
            }

            return response()->json([
                'message' => 'Perfiles de mecánicos obtenidos exitosamente',
                'data' => $profiles->values()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener perfiles de mecánicos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener perfil de mecánico específico
     */
    public function show(Request $request, MechanicProfile $mechanicProfile): JsonResponse
    {
        try {
            $mechanicProfile->load('user:id,name,email,phone,address,city,state,latitude,longitude,created_at');

            $user = $request->user();
            
            // Agregar distancia si es cliente
            if ($user->hasRole('cliente') && $user->latitude && $user->longitude) {
                $mechanicLat = $mechanicProfile->user->latitude;
                $mechanicLng = $mechanicProfile->user->longitude;
                
                if ($mechanicLat && $mechanicLng) {
                    $distance = $this->calculateDistance(
                        $user->latitude, 
                        $user->longitude, 
                        $mechanicLat, 
                        $mechanicLng
                    );
                    $mechanicProfile->distance_km = round($distance, 2);
                }
            }

            return response()->json([
                'message' => 'Perfil de mecánico obtenido exitosamente',
                'data' => $mechanicProfile
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener perfil de mecánico',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear o actualizar perfil de mecánico (solo para usuarios con rol mecánico)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo mecánicos pueden crear/actualizar su perfil
            if (!$user->hasRole('mecanico')) {
                return response()->json([
                    'message' => 'Solo los mecánicos pueden crear un perfil de mecánico'
                ], 403);
            }

            // Verificar si ya tiene perfil
            $existingProfile = $user->mechanicProfile;
            if ($existingProfile) {
                return response()->json([
                    'message' => 'Ya tienes un perfil de mecánico. Usa el método de actualización.'
                ], 400);
            }

            $validatedData = $request->validate([
                'specializations' => 'required|array|min:1',
                'specializations.*' => 'required|string|in:motor,transmision,frenos,suspension,electrico,aire_acondicionado,diagnostico,carroceria,llantas,otros',
                'experience_years' => 'required|integer|min:0|max:50',
                'hourly_rate' => 'required|numeric|min:10|max:500',
                'travel_radius' => 'required|integer|min:1|max:100',
                'emergency_available' => 'required|boolean',
                'bio' => 'nullable|string|max:1000',
                'certifications' => 'nullable|array',
                'certifications.*' => 'string|max:200',
                'tools_owned' => 'nullable|array',
                'tools_owned.*' => 'string|in:basicas,scanner,elevador,compresor,soldadora,multimetro,especializadas',
                'minimum_service_fee' => 'nullable|numeric|min:0|max:200',
                'accepts_weekend_jobs' => 'required|boolean',
                'accepts_night_jobs' => 'required|boolean',
                'availability_schedule' => 'nullable|array',
                'address' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ]);

            $validatedData['user_id'] = $user->id;
            $validatedData['is_available'] = true; // Por defecto disponible
            $validatedData['is_verified'] = false; // Requiere verificación del admin

            $profile = MechanicProfile::create($validatedData);

            return response()->json([
                'message' => 'Perfil de mecánico creado exitosamente',
                'data' => $profile->load('user:id,name,email')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear perfil de mecánico',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener el perfil del mecánico actual
     */
    public function getMyProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo mecánicos pueden acceder a este endpoint
            if (!$user->hasRole('mecanico')) {
                return response()->json([
                    'message' => 'Solo los mecánicos pueden acceder a este endpoint'
                ], 403);
            }

            $profile = $user->mechanicProfile;
            
            if (!$profile) {
                return response()->json([
                    'message' => 'No tienes un perfil de mecánico creado',
                    'data' => null
                ], 404);
            }

            $profile->load('user:id,name,email,phone,address,city,state,latitude,longitude,created_at');

            return response()->json([
                'message' => 'Perfil de mecánico obtenido exitosamente',
                'data' => $profile
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el perfil de mecánico',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el perfil del mecánico actual (sin ID)
     */
    public function updateMyProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo mecánicos pueden actualizar su perfil
            if (!$user->hasRole('mecanico')) {
                return response()->json([
                    'message' => 'Solo los mecánicos pueden actualizar su perfil'
                ], 403);
            }

            $profile = $user->mechanicProfile;
            
            if (!$profile) {
                return response()->json([
                    'message' => 'No tienes un perfil de mecánico creado. Usa el método de creación.'
                ], 404);
            }

            $rules = [
                'specializations' => 'sometimes|array|min:1',
                'specializations.*' => 'required|string|in:motor,transmision,frenos,suspension,electrico,aire_acondicionado,diagnostico,carroceria,llantas,otros',
                'experience_years' => 'sometimes|integer|min:0|max:50',
                'hourly_rate' => 'sometimes|numeric|min:10|max:500',
                'travel_radius' => 'sometimes|integer|min:1|max:100',
                'emergency_available' => 'sometimes|boolean',
                'is_available' => 'sometimes|boolean',
                'bio' => 'nullable|string|max:1000',
                'certifications' => 'nullable|array',
                'certifications.*' => 'string|max:200',
                'tools_owned' => 'nullable|array',
                'tools_owned.*' => 'string|in:basicas,scanner,elevador,compresor,soldadora,multimetro,especializadas',
                'minimum_service_fee' => 'nullable|numeric|min:0|max:200',
                'accepts_weekend_jobs' => 'sometimes|boolean',
                'accepts_night_jobs' => 'sometimes|boolean',
                'availability_schedule' => 'nullable|array',
                'address' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ];

            $validatedData = $request->validate($rules);

            $profile->update($validatedData);

            return response()->json([
                'message' => 'Perfil de mecánico actualizado exitosamente',
                'data' => $profile->fresh()->load('user:id,name,email')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar perfil de mecánico',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Actualizar perfil de mecánico
     */
    public function update(Request $request, MechanicProfile $mechanicProfile): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Verificar permisos
            if ($user->hasRole('mecanico') && $mechanicProfile->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Solo puedes actualizar tu propio perfil'
                ], 403);
            } elseif (!$user->hasRole('mecanico') && !$user->hasRole('administrador')) {
                return response()->json([
                    'message' => 'No tienes permisos para actualizar este perfil'
                ], 403);
            }

            $rules = [
                'specializations' => 'sometimes|array|min:1',
                'specializations.*' => 'required|string|in:motor,transmision,frenos,suspension,electrico,aire_acondicionado,diagnostico,carroceria,llantas,otros',
                'experience_years' => 'sometimes|integer|min:0|max:50',
                'hourly_rate' => 'sometimes|numeric|min:10|max:500',
                'travel_radius' => 'sometimes|integer|min:1|max:100',
                'emergency_available' => 'sometimes|boolean',
                'is_available' => 'sometimes|boolean',
                'bio' => 'nullable|string|max:1000',
                'certifications' => 'nullable|array',
                'certifications.*' => 'string|max:200',
                'tools_owned' => 'nullable|array',
                'tools_owned.*' => 'string|in:basicas,scanner,elevador,compresor,soldadora,multimetro,especializadas',
                'minimum_service_fee' => 'nullable|numeric|min:0|max:200',
                'accepts_weekend_jobs' => 'sometimes|boolean',
                'accepts_night_jobs' => 'sometimes|boolean',
                'availability_schedule' => 'nullable|array',
            ];

            // Solo admin puede cambiar verificación
            if ($user->hasRole('administrador')) {
                $rules['is_verified'] = 'sometimes|boolean';
            }

            $validatedData = $request->validate($rules);

            $mechanicProfile->update($validatedData);

            return response()->json([
                'message' => 'Perfil de mecánico actualizado exitosamente',
                'data' => $mechanicProfile->fresh()->load('user:id,name,email')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar perfil de mecánico',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener mecánicos cercanos para un cliente
     */
    public function getNearbyMechanics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo clientes pueden buscar mecánicos cercanos
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Solo los clientes pueden buscar mecánicos cercanos'
                ], 403);
            }

            $lat = $user->latitude;
            $lng = $user->longitude;
            $radius = $request->get('radius', 25); // 25km por defecto

            if (!$lat || !$lng) {
                return response()->json([
                    'message' => 'Debes actualizar tu ubicación para usar esta función'
                ], 400);
            }

            $query = MechanicProfile::with(['user:id,name,email,phone,address,city,latitude,longitude'])
                ->where('is_available', true)
                ->whereHas('user', function($q) use ($lat, $lng, $radius) {
                    $q->where('is_active', true)
                      ->whereRaw(
                          "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?",
                          [$lat, $lng, $lat, $radius]
                      );
                });

            // Filtros adicionales
            if ($request->has('specialization')) {
                $query->whereJsonContains('specializations', $request->specialization);
            }

            if ($request->has('emergency_only')) {
                $query->where('emergency_available', true);
            }

            if ($request->has('verified_only')) {
                $query->where('is_verified', true);
            }

            $mechanics = $query->get()
                ->map(function($mechanic) use ($lat, $lng) {
                    $mechanicLat = $mechanic->user->latitude;
                    $mechanicLng = $mechanic->user->longitude;
                    
                    if ($mechanicLat && $mechanicLng) {
                        $distance = $this->calculateDistance($lat, $lng, $mechanicLat, $mechanicLng);
                        $mechanic->distance_km = round($distance, 2);
                        
                        // Calcular tiempo estimado de llegada (60 km/h promedio en ciudad)
                        $mechanic->estimated_arrival_minutes = round(($distance / 60) * 60);
                    }
                    
                    return $mechanic;
                })
                ->sortBy('distance_km');

            return response()->json([
                'message' => 'Mecánicos cercanos obtenidos exitosamente',
                'data' => $mechanics->values(),
                'client_location' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'search_radius' => $radius
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener mecánicos cercanos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar disponibilidad de mecánico
     */
    public function updateAvailability(Request $request, MechanicProfile $mechanicProfile): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo el propio mecánico puede cambiar su disponibilidad
            if (!$user->hasRole('mecanico') || $mechanicProfile->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Solo puedes cambiar tu propia disponibilidad'
                ], 403);
            }

            $validatedData = $request->validate([
                'is_available' => 'required|boolean',
                'availability_schedule' => 'nullable|array',
            ]);

            $mechanicProfile->update($validatedData);

            return response()->json([
                'message' => 'Disponibilidad actualizada exitosamente',
                'data' => [
                    'is_available' => $mechanicProfile->is_available,
                    'availability_schedule' => $mechanicProfile->availability_schedule
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar disponibilidad',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener estadísticas del mecánico
     */
    public function getStats(Request $request, MechanicProfile $mechanicProfile): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Verificar permisos
            if ($user->hasRole('mecanico') && $mechanicProfile->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Solo puedes ver tus propias estadísticas'
                ], 403);
            } elseif (!$user->hasRole('mecanico') && !$user->hasRole('administrador')) {
                return response()->json([
                    'message' => 'No tienes permisos para ver estas estadísticas'
                ], 403);
            }

            $stats = [
                'profile' => $mechanicProfile,
                'performance' => [
                    'total_jobs' => $mechanicProfile->total_jobs,
                    'total_reviews' => $mechanicProfile->total_reviews,
                    'rating_average' => $mechanicProfile->rating_average,
                    'response_time_avg' => $mechanicProfile->response_time_avg,
                    'completion_rate' => $mechanicProfile->completion_rate,
                ],
                'earnings' => [
                    'hourly_rate' => $mechanicProfile->hourly_rate,
                    'minimum_service_fee' => $mechanicProfile->minimum_service_fee,
                    'estimated_monthly_revenue' => $mechanicProfile->hourly_rate * 160, // 40h/week
                ],
                'availability' => [
                    'is_available' => $mechanicProfile->is_available,
                    'emergency_available' => $mechanicProfile->emergency_available,
                    'travel_radius' => $mechanicProfile->travel_radius,
                    'availability_schedule' => $mechanicProfile->availability_schedule,
                ]
            ];

            return response()->json([
                'message' => 'Estadísticas obtenidas exitosamente',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
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
