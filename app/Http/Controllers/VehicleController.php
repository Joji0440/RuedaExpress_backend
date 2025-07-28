<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    /**
     * Obtener vehículos del usuario autenticado o todos (para admin/mecánico)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Si es cliente, solo ve sus vehículos
            if ($user->hasRole('cliente')) {
                $vehicles = Vehicle::where('user_id', $user->id)
                    ->with('user:id,name,email')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
            // Si es mecánico o admin, puede ver vehículos según filtros
            elseif ($user->hasRole('mecanico') || $user->hasRole('administrador')) {
                $query = Vehicle::with('user:id,name,email,phone,city,latitude,longitude');
                
                // Filtros opcionales
                if ($request->has('user_id')) {
                    $query->where('user_id', $request->user_id);
                }
                
                if ($request->has('make')) {
                    $query->where('make', 'ILIKE', '%' . $request->make . '%');
                }
                
                if ($request->has('needs_service')) {
                    $query->needsService();
                }
                
                if ($request->has('location') && $user->hasRole('mecanico')) {
                    // Solo mostrar vehículos dentro del radio del mecánico
                    $mechanicProfile = $user->mechanicProfile;
                    if ($mechanicProfile) {
                        $radius = $mechanicProfile->travel_radius;
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
                }
                
                $vehicles = $query->orderBy('created_at', 'desc')->get();
            }
            else {
                return response()->json([
                    'message' => 'No tienes permisos para ver vehículos'
                ], 403);
            }

            return response()->json([
                'message' => 'Vehículos obtenidos exitosamente',
                'data' => $vehicles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener vehículos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un vehículo específico
     */
    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Verificar permisos
            if ($user->hasRole('cliente') && $vehicle->user_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permisos para ver este vehículo'
                ], 403);
            }
            
            $vehicle->load('user:id,name,email,phone,address,city,latitude,longitude');

            return response()->json([
                'message' => 'Vehículo obtenido exitosamente',
                'data' => $vehicle
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener vehículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo vehículo (solo clientes)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo clientes pueden registrar vehículos
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Solo los clientes pueden registrar vehículos'
                ], 403);
            }

            $validatedData = $request->validate([
                'make' => 'required|string|max:50',
                'model' => 'required|string|max:100',
                'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'color' => 'nullable|string|max:30',
                'license_plate' => 'nullable|string|max:20|unique:vehicles,license_plate',
                'vin' => 'nullable|string|max:50|unique:vehicles,vin',
                'engine_size' => 'nullable|string|max:20',
                'fuel_type' => 'required|in:gasoline,diesel,hybrid,electric,other',
                'transmission_type' => 'required|in:manual,automatic,cvt,other',
                'mileage' => 'nullable|integer|min:0',
                'insurance_company' => 'nullable|string|max:100',
                'insurance_policy_number' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:1000',
                'emergency_contacts' => 'nullable|array',
                'emergency_contacts.*.name' => 'required|string|max:100',
                'emergency_contacts.*.phone' => 'required|string|max:20',
                'emergency_contacts.*.type' => 'required|string|in:towing,insurance,family,other',
                'preferences' => 'nullable|array',
            ]);

            $validatedData['user_id'] = $user->id;

            $vehicle = Vehicle::create($validatedData);

            return response()->json([
                'message' => 'Vehículo registrado exitosamente',
                'data' => $vehicle->load('user:id,name,email')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar vehículo',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Actualizar vehículo
     */
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Verificar permisos
            if ($user->hasRole('cliente') && $vehicle->user_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permisos para actualizar este vehículo'
                ], 403);
            }

            $validatedData = $request->validate([
                'make' => 'sometimes|required|string|max:50',
                'model' => 'sometimes|required|string|max:100',
                'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
                'color' => 'nullable|string|max:30',
                'license_plate' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('vehicles')->ignore($vehicle->id)
                ],
                'vin' => [
                    'nullable',
                    'string',
                    'max:50',
                    Rule::unique('vehicles')->ignore($vehicle->id)
                ],
                'engine_size' => 'nullable|string|max:20',
                'fuel_type' => 'sometimes|required|in:gasoline,diesel,hybrid,electric,other',
                'transmission_type' => 'sometimes|required|in:manual,automatic,cvt,other',
                'mileage' => 'nullable|integer|min:0',
                'insurance_company' => 'nullable|string|max:100',
                'insurance_policy_number' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:1000',
                'emergency_contacts' => 'nullable|array',
                'emergency_contacts.*.name' => 'required|string|max:100',
                'emergency_contacts.*.phone' => 'required|string|max:20',
                'emergency_contacts.*.type' => 'required|string|in:towing,insurance,family,other',
                'preferences' => 'nullable|array',
                'is_active' => 'sometimes|boolean',
            ]);

            $vehicle->update($validatedData);

            return response()->json([
                'message' => 'Vehículo actualizado exitosamente',
                'data' => $vehicle->fresh()->load('user:id,name,email')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar vehículo',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Eliminar vehículo
     */
    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Verificar permisos
            if ($user->hasRole('cliente') && $vehicle->user_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permisos para eliminar este vehículo'
                ], 403);
            }

            $vehicle->delete();

            return response()->json([
                'message' => 'Vehículo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar vehículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar entrada al historial de servicio
     */
    public function addServiceRecord(Request $request, Vehicle $vehicle): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo el propietario o un mecánico puede agregar registros
            if ($user->hasRole('cliente') && $vehicle->user_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permisos para agregar registros a este vehículo'
                ], 403);
            }

            $validatedData = $request->validate([
                'service' => 'required|string|max:200',
                'description' => 'nullable|string|max:1000',
                'cost' => 'nullable|numeric|min:0',
                'mileage' => 'nullable|integer|min:0',
                'mechanic_name' => 'nullable|string|max:100',
                'service_date' => 'nullable|date',
            ]);

            $serviceRecord = array_merge($validatedData, [
                'date' => $validatedData['service_date'] ?? now()->toDateString(),
                'added_by' => $user->name,
                'added_by_role' => $user->roles->first()->name ?? 'unknown',
                'created_at' => now()->toISOString(),
            ]);

            // Obtener historial actual y agregar nuevo registro
            $currentHistory = $vehicle->service_history ?? [];
            $currentHistory[] = $serviceRecord;

            $vehicle->update([
                'service_history' => $currentHistory,
                'last_service_date' => $serviceRecord['date'],
                'mileage' => $validatedData['mileage'] ?? $vehicle->mileage,
            ]);

            return response()->json([
                'message' => 'Registro de servicio agregado exitosamente',
                'data' => $vehicle->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar registro de servicio',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Obtener vehículos cercanos (para mecánicos)
     */
    public function getNearbyVehicles(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo mecánicos pueden usar esta función
            if (!$user->hasRole('mecanico')) {
                return response()->json([
                    'message' => 'Solo los mecánicos pueden buscar vehículos cercanos'
                ], 403);
            }

            $mechanicProfile = $user->mechanicProfile;
            if (!$mechanicProfile) {
                return response()->json([
                    'message' => 'Debes completar tu perfil de mecánico primero'
                ], 400);
            }

            $radius = $request->get('radius', $mechanicProfile->travel_radius);
            $lat = $user->latitude;
            $lng = $user->longitude;

            if (!$lat || !$lng) {
                return response()->json([
                    'message' => 'Debes actualizar tu ubicación para usar esta función'
                ], 400);
            }

            $vehicles = Vehicle::with(['user:id,name,email,phone,address,city,latitude,longitude'])
                ->whereHas('user', function($query) use ($lat, $lng, $radius) {
                    $query->whereRaw(
                        "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?",
                        [$lat, $lng, $lat, $radius]
                    );
                })
                ->where('is_active', true)
                ->get()
                ->map(function($vehicle) use ($lat, $lng) {
                    $vehicleLat = $vehicle->user->latitude;
                    $vehicleLng = $vehicle->user->longitude;
                    
                    if ($vehicleLat && $vehicleLng) {
                        $distance = $this->calculateDistance($lat, $lng, $vehicleLat, $vehicleLng);
                        $vehicle->distance_km = round($distance, 2);
                    }
                    
                    return $vehicle;
                })
                ->sortBy('distance_km');

            return response()->json([
                'message' => 'Vehículos cercanos obtenidos exitosamente',
                'data' => $vehicles->values(),
                'mechanic_location' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'travel_radius' => $radius
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener vehículos cercanos',
                'error' => $e->getMessage()
            ], 500);
        }
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
