<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MechanicProfile;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * Actualizar la ubicación del mecánico
     */
    public function updateMechanicLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de ubicación inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $mechanicProfile = $user->mechanicProfile;

        if (!$mechanicProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil de mecánico no encontrado'
            ], 404);
        }

        $updated = $mechanicProfile->updateLocation(
            $request->latitude,
            $request->longitude,
            $request->address
        );

        if ($updated) {
            return response()->json([
                'success' => true,
                'message' => 'Ubicación actualizada correctamente',
                'data' => [
                    'latitude' => $mechanicProfile->latitude,
                    'longitude' => $mechanicProfile->longitude,
                    'address' => $mechanicProfile->address,
                    'updated_at' => $mechanicProfile->location_updated_at,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar la ubicación'
        ], 500);
    }

    /**
     * Obtener la ubicación actual del mecánico
     */
    public function getMechanicLocation(): JsonResponse
    {
        $user = Auth::user();
        $mechanicProfile = $user->mechanicProfile;

        if (!$mechanicProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil de mecánico no encontrado'
            ], 404);
        }

        if (!$mechanicProfile->hasLocation()) {
            return response()->json([
                'success' => false,
                'message' => 'Ubicación no configurada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'latitude' => $mechanicProfile->latitude,
                'longitude' => $mechanicProfile->longitude,
                'address' => $mechanicProfile->address,
                'travel_radius_km' => $mechanicProfile->travel_radius,
                'updated_at' => $mechanicProfile->location_updated_at,
            ]
        ]);
    }

    /**
     * Calcular distancia entre mecánico y una solicitud de servicio
     */
    public function calculateDistanceToService(ServiceRequest $serviceRequest): JsonResponse
    {
        $user = Auth::user();
        $mechanicProfile = $user->mechanicProfile;

        if (!$mechanicProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil de mecánico no encontrado'
            ], 404);
        }

        if (!$mechanicProfile->hasLocation()) {
            return response()->json([
                'success' => false,
                'message' => 'Ubicación del mecánico no configurada'
            ], 400);
        }

        if (!$serviceRequest->hasCoordinates()) {
            return response()->json([
                'success' => false,
                'message' => 'Coordenadas del servicio no disponibles'
            ], 400);
        }

        $travelInfo = $serviceRequest->getDistanceFromMechanic($mechanicProfile);

        return response()->json([
            'success' => true,
            'data' => $travelInfo
        ]);
    }

    /**
     * Obtener servicios cercanos para el mecánico actual
     */
    public function getNearbyServices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'radius_km' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:pending,accepted,in_progress',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $mechanicProfile = $user->mechanicProfile;

        if (!$mechanicProfile || !$mechanicProfile->hasLocation()) {
            return response()->json([
                'success' => false,
                'message' => 'Ubicación del mecánico no configurada'
            ], 400);
        }

        $radiusKm = $request->radius_km ?? $mechanicProfile->travel_radius ?? 10;
        $status = $request->status ?? 'pending';

        $services = ServiceRequest::withCoordinates()
            ->nearLocation(
                $mechanicProfile->latitude,
                $mechanicProfile->longitude,
                $radiusKm
            )
            ->where('status', $status)
            ->with(['client', 'vehicle'])
            ->get();

        // Agregar información de distancia a cada servicio
        $servicesWithDistance = $services->map(function ($service) use ($mechanicProfile) {
            $travelInfo = $service->getDistanceFromMechanic($mechanicProfile);
            
            return [
                'id' => $service->id,
                'title' => $service->title,
                'description' => $service->description,
                'service_type' => $service->service_type,
                'urgency_level' => $service->urgency_level,
                'urgency_label' => $service->urgency_label,
                'is_emergency' => $service->is_emergency,
                'budget_max' => $service->budget_max,
                'estimated_duration_hours' => $service->estimated_duration_hours,
                'preferred_date' => $service->preferred_date,
                'location_address' => $service->location_address,
                'location_notes' => $service->location_notes,
                'client' => $service->client,
                'vehicle' => $service->vehicle,
                'travel_info' => $travelInfo,
                'created_at' => $service->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'services' => $servicesWithDistance,
                'search_params' => [
                    'radius_km' => $radiusKm,
                    'status' => $status,
                    'mechanic_location' => [
                        'latitude' => $mechanicProfile->latitude,
                        'longitude' => $mechanicProfile->longitude,
                        'address' => $mechanicProfile->address,
                    ]
                ]
            ]
        ]);
    }

    /**
     * Obtener mecánicos cercanos a una ubicación (para clientes)
     */
    public function getNearbyMechanics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|integer|min:1|max:100',
            'specialization' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radiusKm = $request->radius_km ?? 20;
        $specialization = $request->specialization;

        $mechanicsQuery = MechanicProfile::withLocation()
            ->verified()
            ->canServeLocation($latitude, $longitude);

        if ($specialization) {
            $mechanicsQuery->withSpecialization($specialization);
        }

        $mechanics = $mechanicsQuery->with('user')->get();

        // Agregar información de distancia a cada mecánico
        $mechanicsWithDistance = $mechanics->map(function ($mechanic) use ($latitude, $longitude) {
            $travelInfo = $mechanic->getTravelInfo($latitude, $longitude);
            
            return [
                'id' => $mechanic->id,
                'user' => $mechanic->user,
                'specializations' => $mechanic->specializations,
                'formatted_specializations' => $mechanic->formatted_specializations,
                'experience_years' => $mechanic->experience_years,
                'hourly_rate' => $mechanic->hourly_rate,
                'rating_average' => $mechanic->rating_average,
                'total_jobs' => $mechanic->total_jobs,
                'total_reviews' => $mechanic->total_reviews,
                'emergency_available' => $mechanic->emergency_available,
                'travel_radius' => $mechanic->travel_radius,
                'bio' => $mechanic->bio,
                'travel_info' => $travelInfo,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'mechanics' => $mechanicsWithDistance,
                'search_params' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_km' => $radiusKm,
                    'specialization' => $specialization,
                ]
            ]
        ]);
    }
}
