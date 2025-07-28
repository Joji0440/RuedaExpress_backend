<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\MechanicProfile;
use App\Services\DistanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ServiceRequestController extends Controller
{
    use DistanceCalculator;
    /**
     * Display a listing of the service requests.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = ServiceRequest::with(['client', 'mechanic', 'vehicle']);

            // Filtrar por rol del usuario
            if ($user->hasRole('cliente')) {
                $query->where('client_id', $user->id);
            } elseif ($user->hasRole('mecanico')) {
                $query->where('mechanic_id', $user->id);
            } elseif (!$user->hasRole('administrador')) {
                return response()->json([
                    'message' => 'No tienes permisos para ver estas solicitudes'
                ], 403);
            }

            // Aplicar filtros
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && !empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            $serviceRequests = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'message' => 'Solicitudes de servicio obtenidas exitosamente',
                'data' => $serviceRequests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener solicitudes de servicio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created service request.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Solo clientes pueden crear solicitudes
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

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'service_type' => 'required|string',
                'urgency_level' => 'required|in:baja,media,alta,critica',
                'estimated_duration_hours' => 'required|numeric|min:0.5|max:48',
                'budget_max' => 'required|numeric|min:0',
                'is_emergency' => 'boolean',
                'preferred_date' => 'nullable|date|after:today',
                'location_address' => 'nullable|string|max:500',
                'location_notes' => 'nullable|string|max:1000',
                'location_latitude' => 'nullable|numeric|between:-90,90',
                'location_longitude' => 'nullable|numeric|between:-180,180',
                'vehicle_id' => 'nullable|exists:vehicles,id',
                'preferred_mechanic_id' => 'nullable|exists:users,id'
            ]);

            // DEBUG: Log de los datos recibidos
            \Illuminate\Support\Facades\Log::info('🔍 BACKEND - ServiceRequest CREATE - Datos recibidos:', [
                'all_request_data' => $request->all(),
                'validated_data' => $validated,
                'location_latitude' => $request->input('location_latitude'),
                'location_longitude' => $request->input('location_longitude'),
                'has_latitude' => $request->has('location_latitude'),
                'has_longitude' => $request->has('location_longitude'),
                'lat_type' => gettype($request->input('location_latitude')),
                'lng_type' => gettype($request->input('location_longitude')),
                'lat_value' => var_export($request->input('location_latitude'), true),
                'lng_value' => var_export($request->input('location_longitude'), true),
            ]);

            $validated['client_id'] = $user->id;
            $validated['status'] = 'pending';

            // DEBUG: Log antes de crear en BD
            \Illuminate\Support\Facades\Log::info('💾 BACKEND - Datos que se van a guardar en BD:', [
                'final_data_for_db' => $validated,
                'coordinates_to_save' => [
                    'latitude' => $validated['location_latitude'] ?? 'NO_SET',
                    'longitude' => $validated['location_longitude'] ?? 'NO_SET'
                ]
            ]);

            $serviceRequest = ServiceRequest::create($validated);

            // DEBUG: Log después de crear en BD
            \Illuminate\Support\Facades\Log::info('✅ BACKEND - ServiceRequest creado en BD:', [
                'service_id' => $serviceRequest->id,
                'saved_latitude' => $serviceRequest->location_latitude,
                'saved_longitude' => $serviceRequest->location_longitude,
                'coordinates_saved_correctly' => [
                    'latitude_ok' => !is_null($serviceRequest->location_latitude),
                    'longitude_ok' => !is_null($serviceRequest->location_longitude),
                    'both_coordinates' => !is_null($serviceRequest->location_latitude) && !is_null($serviceRequest->location_longitude)
                ]
            ]);
            $serviceRequest->load(['client', 'vehicle']);

            return response()->json([
                'message' => 'Solicitud de servicio creada exitosamente',
                'data' => $serviceRequest
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Datos de validación inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear solicitud de servicio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified service request.
     */
    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verificar permisos
            if (!$user->hasRole('administrador') && 
                $serviceRequest->client_id !== $user->id && 
                $serviceRequest->mechanic_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permisos para ver esta solicitud'
                ], 403);
            }

            $serviceRequest->load(['client', 'mechanic', 'vehicle']);

            return response()->json([
                'message' => 'Solicitud de servicio obtenida exitosamente',
                'data' => $serviceRequest
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener solicitud de servicio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified service request.
     */
    public function update(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        try {
            $user = Auth::user();

            // Solo el cliente propietario puede actualizar (y solo si está pendiente)
            if ($serviceRequest->client_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permisos para actualizar esta solicitud'
                ], 403);
            }

            if ($serviceRequest->status !== 'pending') {
                return response()->json([
                    'message' => 'Solo se pueden actualizar solicitudes pendientes'
                ], 422);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'service_type' => 'sometimes|string',
                'urgency_level' => 'sometimes|in:baja,media,alta,critica',
                'estimated_duration_hours' => 'sometimes|numeric|min:0.5|max:48',
                'budget_max' => 'sometimes|numeric|min:0',
                'is_emergency' => 'sometimes|boolean',
                'preferred_date' => 'nullable|date|after:today',
                'location_address' => 'nullable|string|max:500',
                'location_notes' => 'nullable|string|max:1000',
                'location_latitude' => 'nullable|numeric|between:-90,90',
                'location_longitude' => 'nullable|numeric|between:-180,180',
                'vehicle_id' => 'nullable|exists:vehicles,id',
                'preferred_mechanic_id' => 'nullable|exists:users,id'
            ]);

            $serviceRequest->update($validated);
            $serviceRequest->load(['client', 'mechanic', 'vehicle']);

            return response()->json([
                'message' => 'Solicitud de servicio actualizada exitosamente',
                'data' => $serviceRequest
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Datos de validación inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar solicitud de servicio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified service request.
     */
    public function destroy(ServiceRequest $serviceRequest): JsonResponse
    {
        try {
            $user = Auth::user();

            // Solo el cliente propietario puede eliminar (y solo si está pendiente)
            if ($serviceRequest->client_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permisos para eliminar esta solicitud'
                ], 403);
            }

            if (!in_array($serviceRequest->status, ['pending', 'cancelled'])) {
                return response()->json([
                    'message' => 'Solo se pueden eliminar solicitudes pendientes o canceladas'
                ], 422);
            }

            $serviceRequest->delete();

            return response()->json([
                'message' => 'Solicitud de servicio eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar solicitud de servicio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the status of a service request.
     */
    public function updateStatus(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        try {
            $user = Auth::user();

            $validated = $request->validate([
                'status' => 'required|in:pending,accepted,in_progress,completed,cancelled,rejected'
            ]);

            $newStatus = $validated['status'];

            // Verificar permisos según el estado
            switch ($newStatus) {
                case 'cancelled':
                    // Solo el cliente puede cancelar
                    if ($serviceRequest->client_id !== $user->id) {
                        return response()->json([
                            'message' => 'Solo el cliente puede cancelar la solicitud'
                        ], 403);
                    }
                    break;

                case 'accepted':
                case 'rejected':
                case 'in_progress':
                case 'completed':
                    // Solo el mecánico asignado puede cambiar estos estados
                    if (!$user->hasRole('mecanico') || $serviceRequest->mechanic_id !== $user->id) {
                        return response()->json([
                            'message' => 'Solo el mecánico asignado puede cambiar este estado'
                        ], 403);
                    }
                    break;

                default:
                    return response()->json([
                        'message' => 'Estado no válido'
                    ], 422);
            }

            $serviceRequest->update(['status' => $newStatus]);
            $serviceRequest->load(['client', 'mechanic', 'vehicle']);

            return response()->json([
                'message' => 'Estado de solicitud actualizado exitosamente',
                'data' => $serviceRequest
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Datos de validación inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar estado de solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener solicitudes disponibles para mecánicos con filtros de tiempo
     */
    public function getAvailableRequests(Request $request)
    {
        try {
            $user = $request->user();

            // Verificar que el usuario (mecánico) esté activo
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Tu cuenta está desactivada. No puedes ver solicitudes disponibles'
                ], 403);
            }

            // Parámetros de paginación y filtros
            $perPage = min($request->input('per_page', 10), 20); // Máximo 20 por página
            $page = $request->input('page', 1);

            // Calcular el tiempo límite (5 horas atrás)
            $timeLimit = now()->subHours(5);

            // Obtener solicitudes pendientes sin mecánico asignado y que tengan máximo 5 horas
            $query = ServiceRequest::where('status', 'pending')
                ->whereNull('mechanic_id')
                ->where('created_at', '>=', $timeLimit) // Solo solicitudes de las últimas 5 horas
                ->with(['client', 'vehicle'])
                ->orderBy('created_at', 'desc');

            // Aplicar filtro adicional si se proporciona
            if ($request->has('urgency_level') && $request->urgency_level !== 'all') {
                $query->where('urgency_level', $request->urgency_level);
            }

            $availableRequests = $query->paginate($perPage, ['*'], 'page', $page);

            // Agregar información de tiempo transcurrido a cada solicitud
            $availableRequests->getCollection()->transform(function ($request) {
                $request->time_elapsed = $this->calculateTimeElapsed($request->created_at);
                return $request;
            });

            return response()->json([
                'message' => 'Solicitudes disponibles obtenidas exitosamente',
                'data' => $availableRequests,
                'meta' => [
                    'time_filter' => '5 horas',
                    'current_time' => now()->toISOString(),
                    'cutoff_time' => $timeLimit->toISOString()
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
     * Calcular tiempo transcurrido desde la creación
     */
    private function calculateTimeElapsed($createdAt)
    {
        $now = now();
        $created = \Carbon\Carbon::parse($createdAt);
        
        $diffInMinutes = $created->diffInMinutes($now);
        $diffInHours = $created->diffInHours($now);
        
        if ($diffInMinutes < 60) {
            return [
                'value' => $diffInMinutes,
                'unit' => 'minutos',
                'formatted' => $diffInMinutes . ' min',
                'is_urgent' => $diffInHours >= 4 // Urgente si tiene más de 4 horas
            ];
        } else {
            return [
                'value' => $diffInHours,
                'unit' => 'horas',
                'formatted' => $diffInHours . 'h ' . ($diffInMinutes % 60) . 'min',
                'is_urgent' => $diffInHours >= 4
            ];
        }
    }

    /**
     * Aceptar una solicitud de servicio
     */
    public function acceptRequest(Request $request, ServiceRequest $serviceRequest)
    {
        try {
            $user = $request->user();

            // Verificar que el usuario (mecánico) esté activo
            if (!$user->is_active) {
                return response()->json([
                    'message' => 'Tu cuenta está desactivada. No puedes aceptar solicitudes de servicio'
                ], 403);
            }

            // Verificar que la solicitud esté disponible
            if ($serviceRequest->status !== 'pending' || $serviceRequest->mechanic_id) {
                return response()->json([
                    'message' => 'Esta solicitud ya no está disponible'
                ], 409);
            }

            // Asignar el mecánico y cambiar el estado
            $serviceRequest->update([
                'mechanic_id' => $user->id,
                'status' => 'accepted'
            ]);

            $serviceRequest->load(['client', 'mechanic', 'vehicle']);

            return response()->json([
                'message' => 'Solicitud aceptada exitosamente',
                'data' => $serviceRequest
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al aceptar solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar una solicitud de servicio
     */
    public function rejectRequest(Request $request, ServiceRequest $serviceRequest)
    {
        try {
            $user = $request->user();

            // Verificar que la solicitud esté disponible
            if ($serviceRequest->status !== 'pending') {
                return response()->json([
                    'message' => 'Esta solicitud ya no está disponible para rechazar'
                ], 409);
            }

            // Cambiar el estado a rechazado
            $serviceRequest->update([
                'status' => 'rejected'
            ]);

            $serviceRequest->load(['client', 'vehicle']);

            return response()->json([
                'message' => 'Solicitud rechazada exitosamente',
                'data' => $serviceRequest
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al rechazar solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate distance between mechanic and service location
     */
    public function calculateDistanceToService(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        try {
            $user = Auth::user();

            // DEBUG: Log del usuario y servicio
            \Illuminate\Support\Facades\Log::info('🧮 DISTANCE CALC - Iniciando cálculo de distancia:', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'service_id' => $serviceRequest->id,
                'service_title' => $serviceRequest->title,
                'service_coordinates' => [
                    'latitude' => $serviceRequest->location_latitude,
                    'longitude' => $serviceRequest->location_longitude
                ]
            ]);

            // Solo mecánicos pueden calcular distancias (temporalmente comentado para testing)
            // if (!$user->hasRole('mecanico')) {
            //     \Illuminate\Support\Facades\Log::warning('🚫 DISTANCE CALC - Usuario no es mecánico:', [
            //         'user_id' => $user->id,
            //         'user_roles' => $user->roles ?? 'NO_ROLES'
            //     ]);
            //     return response()->json([
            //         'message' => 'Solo los mecánicos pueden calcular distancias'
            //     ], 403);
            // }

            // Verificar que el servicio tenga coordenadas
            if (!$serviceRequest->location_latitude || !$serviceRequest->location_longitude) {
                \Illuminate\Support\Facades\Log::error('📍 DISTANCE CALC - Servicio sin coordenadas:', [
                    'service_id' => $serviceRequest->id,
                    'has_latitude' => !is_null($serviceRequest->location_latitude),
                    'has_longitude' => !is_null($serviceRequest->location_longitude),
                    'latitude_value' => $serviceRequest->location_latitude,
                    'longitude_value' => $serviceRequest->location_longitude
                ]);
                return response()->json([
                    'message' => 'El servicio no tiene coordenadas de ubicación'
                ], 422);
            }

            // Obtener el perfil del mecánico (o usar coordenadas de ejemplo para testing)
            $mechanicProfile = $user->mechanicProfile;
            
            // Si no hay perfil de mecánico, usar coordenadas de ejemplo en Guayaquil
            if (!$mechanicProfile) {
                \Illuminate\Support\Facades\Log::info('👤 DISTANCE CALC - Sin perfil, usando coordenadas de ejemplo:', [
                    'user_id' => $user->id,
                    'example_coordinates' => ['lat' => -2.1419, 'lng' => -79.8919]
                ]);
                
                $mechanicLat = -2.1419; // Centro de Guayaquil
                $mechanicLng = -79.8919;
                $travelRadius = 25; // 25km por defecto
            } else {
                // DEBUG: Log del perfil del mecánico
                \Illuminate\Support\Facades\Log::info('👤 DISTANCE CALC - Perfil del mecánico:', [
                    'mechanic_profile_id' => $mechanicProfile->id,
                    'mechanic_coordinates' => [
                        'latitude' => $mechanicProfile->latitude,
                        'longitude' => $mechanicProfile->longitude
                    ],
                    'travel_radius' => $mechanicProfile->travel_radius,
                    'has_coordinates' => !is_null($mechanicProfile->latitude) && !is_null($mechanicProfile->longitude)
                ]);

                // Si el mecánico no tiene coordenadas, usar las de ejemplo
                if (!$mechanicProfile->latitude || !$mechanicProfile->longitude) {
                    \Illuminate\Support\Facades\Log::warning('📍 DISTANCE CALC - Mecánico sin coordenadas, usando ejemplo:', [
                        'mechanic_profile_id' => $mechanicProfile->id,
                        'example_coordinates' => ['lat' => -2.1419, 'lng' => -79.8919]
                    ]);
                    
                    $mechanicLat = -2.1419;
                    $mechanicLng = -79.8919;
                    $travelRadius = $mechanicProfile->travel_radius ?? 25;
                } else {
                    $mechanicLat = $mechanicProfile->latitude;
                    $mechanicLng = $mechanicProfile->longitude;
                    $travelRadius = $mechanicProfile->travel_radius;
                }
            }

            // Calcular distancia usando el trait DistanceCalculator
            $distance = $this->calculateDistance(
                $mechanicLat,
                $mechanicLng,
                $serviceRequest->location_latitude,
                $serviceRequest->location_longitude
            );

            // Validar radio de viaje
            $radiusValidation = $this->validateRadius($distance, $travelRadius);

            // DEBUG: Log del resultado
            \Illuminate\Support\Facades\Log::info('✅ DISTANCE CALC - Cálculo completado:', [
                'distance_km' => $distance,
                'mechanic_coords' => ['lat' => $mechanicLat, 'lng' => $mechanicLng],
                'service_coords' => [
                    'lat' => $serviceRequest->location_latitude, 
                    'lng' => $serviceRequest->location_longitude
                ],
                'radius_validation' => $radiusValidation
            ]);

            return response()->json([
                'message' => 'Distancia calculada exitosamente',
                'data' => [
                    'distance' => [
                        'km' => $distance,
                        'formatted' => $this->formatDistance($distance)
                    ],
                    'radius_validation' => $radiusValidation,
                    'travel_radius_km' => $travelRadius,
                    'mechanic_location' => [
                        'latitude' => $mechanicLat,
                        'longitude' => $mechanicLng
                    ],
                    'service_location' => [
                        'latitude' => $serviceRequest->location_latitude,
                        'longitude' => $serviceRequest->location_longitude
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('💥 DISTANCE CALC - Error:', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al calcular distancia',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get services within mechanic's travel radius with distance calculations
     */
    public function getServicesWithDistance(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Solo mecánicos pueden ver servicios con distancia
            if (!$user->hasRole('mecanico')) {
                return response()->json([
                    'message' => 'Solo los mecánicos pueden ver servicios con distancia'
                ], 403);
            }

            $mechanicProfile = $user->mechanicProfile;
            if (!$mechanicProfile) {
                return response()->json([
                    'message' => 'No se encontró el perfil del mecánico'
                ], 404);
            }

            // Verificar que el mecánico tenga coordenadas
            if (!$mechanicProfile->latitude || !$mechanicProfile->longitude) {
                return response()->json([
                    'message' => 'El mecánico debe configurar su ubicación primero'
                ], 422);
            }

            // Obtener servicios pendientes con coordenadas
            $query = ServiceRequest::where('status', 'pending')
                ->whereNotNull('location_latitude')
                ->whereNotNull('location_longitude')
                ->with(['client', 'vehicle']);

            // Filtrar por búsqueda si se proporciona
            if ($request->has('search') && $request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            $services = $query->get();

            // Calcular distancia para cada servicio
            $servicesWithDistance = $services->map(function ($service) use ($mechanicProfile) {
                $distance = $this->calculateDistance(
                    $mechanicProfile->latitude,
                    $mechanicProfile->longitude,
                    $service->location_latitude,
                    $service->location_longitude
                );

                $travelTime = $this->calculateTravelTime($distance);
                $radiusValidation = $this->validateRadius($distance, $mechanicProfile->travel_radius);

                return [
                    'service' => $service,
                    'distance_info' => [
                        'distance' => [
                            'km' => $distance,
                            'formatted' => $this->formatDistance($distance)
                        ],
                        'travel_time' => $travelTime,
                        'radius_validation' => $radiusValidation,
                        'travel_radius_km' => $mechanicProfile->travel_radius
                    ]
                ];
            });

            // Filtrar servicios dentro del radio si se solicita
            if ($request->boolean('within_radius_only')) {
                $servicesWithDistance = $servicesWithDistance->filter(function ($item) {
                    return $item['distance_info']['radius_validation']['within_radius'];
                });
            }

            // Ordenar por distancia
            $servicesWithDistance = $servicesWithDistance->sortBy(function ($item) {
                return $item['distance_info']['distance']['km'];
            })->values();

            return response()->json([
                'message' => 'Servicios con distancia obtenidos exitosamente',
                'data' => $servicesWithDistance,
                'mechanic_location' => [
                    'latitude' => $mechanicProfile->latitude,
                    'longitude' => $mechanicProfile->longitude
                ],
                'travel_radius_km' => $mechanicProfile->travel_radius
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener servicios con distancia',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
