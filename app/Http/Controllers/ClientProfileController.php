<?php

namespace App\Http\Controllers;

use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ClientProfileController extends Controller
{
    /**
     * Obtener el perfil del cliente autenticado
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a esta función.'
                ], 403);
            }

            $profile = ClientProfile::with(['user:id,name,email,phone,address,city,state,postal_code,latitude,longitude,profile_photo_path'])
                ->where('user_id', $user->id)
                ->first();

            if (!$profile) {
                // Crear perfil básico si no existe
                $profile = $this->createDefaultProfile($user);
            }

            // Agregar estadísticas reales calculadas dinámicamente
            $realStats = $profile->calculateRealStats();
            $profileData = $profile->toArray();
            
            // Sobrescribir con estadísticas reales
            foreach ($realStats as $key => $value) {
                $profileData[$key] = $value;
            }

            return response()->json([
                'data' => $profileData,
                'message' => 'Perfil de cliente obtenido exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo perfil de cliente: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error procesando la solicitud'
            ], 500);
        }
    }

    /**
     * Actualizar el perfil del cliente
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a esta función.'
                ], 403);
            }

            // Validar datos de entrada
            $validatedData = $request->validate([
                'birth_date' => 'nullable|date|before:today',
                'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
                'occupation' => 'nullable|string|max:100',
                'bio' => 'nullable|string|max:1000',
                
                // Contacto de emergencia
                'emergency_contact_name' => 'nullable|string|max:100',
                'emergency_contact_phone' => 'nullable|string|max:20',
                'emergency_contact_relationship' => 'nullable|string|max:50',
                
                // Preferencias de servicio
                'preferred_service_times' => 'nullable|array',
                'communication_preference' => 'nullable|in:phone,email,sms,app_notification',
                'notifications_enabled' => 'nullable|boolean',
                'email_notifications' => 'nullable|boolean',
                'sms_notifications' => 'nullable|boolean',
                
                // Configuración de servicios
                'preferred_max_cost' => 'nullable|numeric|min:0|max:99999.99',
                'preferred_mechanic_radius' => 'nullable|integer|min:1|max:100',
                'service_preferences' => 'nullable|array',
                'auto_accept_quotes' => 'nullable|boolean',
                
                // Ubicación laboral
                'work_address' => 'nullable|string|max:255',
                'work_city' => 'nullable|string|max:100',
                'work_latitude' => 'nullable|numeric|between:-90,90',
                'work_longitude' => 'nullable|numeric|between:-180,180',
                
                // Personalización
                'theme_preference' => 'nullable|in:light,dark,auto',
                'language_preference' => 'nullable|string|max:10',
                'dashboard_layout' => 'nullable|array',
                
                // Configuración de privacidad
                'profile_visibility' => 'nullable|boolean',
                'show_location' => 'nullable|boolean',
                'allow_mechanic_recommendations' => 'nullable|boolean',
            ]);

            // Obtener o crear perfil
            $profile = ClientProfile::where('user_id', $user->id)->first();
            
            if (!$profile) {
                $profile = $this->createDefaultProfile($user);
            }

            // Actualizar perfil
            $profile->update($validatedData);

            // Recargar con relaciones
            $profile->load(['user:id,name,email,phone,address,city,state,postal_code,latitude,longitude,profile_photo_path']);

            Log::info('Perfil de cliente actualizado', [
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'updated_fields' => array_keys($validatedData)
            ]);

            return response()->json([
                'data' => $profile,
                'message' => 'Perfil actualizado exitosamente'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error actualizando perfil de cliente: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error procesando la solicitud'
            ], 500);
        }
    }

    /**
     * Obtener configuración del dashboard
     */
    public function getDashboardConfig(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a esta función.'
                ], 403);
            }

            $profile = ClientProfile::where('user_id', $user->id)->first();
            
            if (!$profile) {
                $profile = $this->createDefaultProfile($user);
            }

            $config = $profile->getDashboardConfig();

            return response()->json([
                'data' => $config,
                'message' => 'Configuración del dashboard obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo configuración del dashboard: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error procesando la solicitud'
            ], 500);
        }
    }

    /**
     * Actualizar configuración del dashboard
     */
    public function updateDashboardConfig(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a esta función.'
                ], 403);
            }

            $validatedData = $request->validate([
                'dashboard_layout' => 'required|array',
                'theme_preference' => 'nullable|in:light,dark,auto',
                'language_preference' => 'nullable|string|max:10',
            ]);

            $profile = ClientProfile::where('user_id', $user->id)->first();
            
            if (!$profile) {
                $profile = $this->createDefaultProfile($user);
            }

            $profile->update($validatedData);

            return response()->json([
                'data' => $profile->getDashboardConfig(),
                'message' => 'Configuración del dashboard actualizada exitosamente'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error actualizando configuración del dashboard: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error procesando la solicitud'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas del cliente
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a esta función.'
                ], 403);
            }

            $profile = ClientProfile::where('user_id', $user->id)->first();
            
            if (!$profile) {
                $profile = $this->createDefaultProfile($user);
            }

            // Obtener estadísticas reales
            $realStats = $profile->calculateRealStats();
            
            $stats = [
                'total_services_requested' => $realStats['total_services_requested'],
                'total_services_completed' => $realStats['total_services_completed'],
                'completion_rate' => $realStats['service_completion_rate'],
                'total_spent' => $realStats['total_spent'],
                'formatted_total_spent' => $realStats['formatted_total_spent'],
                'average_rating_given' => $realStats['average_rating_given'],
                'total_ratings_given' => $realStats['total_ratings_given'],
                'loyalty_points' => $profile->getAvailableLoyaltyPoints(),
                'account_type' => $profile->account_type,
                'is_premium' => $profile->is_premium,
                'age' => $profile->age,
            ];

            return response()->json([
                'data' => $stats,
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas del cliente: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error procesando la solicitud'
            ], 500);
        }
    }

    /**
     * Gestionar puntos de lealtad
     */
    public function manageLoyaltyPoints(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->hasRole('cliente')) {
                return response()->json([
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a esta función.'
                ], 403);
            }

            $validatedData = $request->validate([
                'action' => 'required|in:use,history',
                'points' => 'required_if:action,use|integer|min:1',
                'reason' => 'required_if:action,use|string|max:255',
            ]);

            $profile = ClientProfile::where('user_id', $user->id)->first();
            
            if (!$profile) {
                return response()->json([
                    'message' => 'Perfil no encontrado'
                ], 404);
            }

            if ($validatedData['action'] === 'use') {
                $success = $profile->useLoyaltyPoints($validatedData['points'], $validatedData['reason']);
                
                if (!$success) {
                    return response()->json([
                        'message' => 'Puntos insuficientes',
                        'available_points' => $profile->getAvailableLoyaltyPoints()
                    ], 400);
                }

                return response()->json([
                    'message' => 'Puntos utilizados exitosamente',
                    'remaining_points' => $profile->getAvailableLoyaltyPoints()
                ]);
            }

            // Obtener historial
            $history = $profile->loyalty_points['history'] ?? [];
            
            return response()->json([
                'data' => [
                    'available_points' => $profile->getAvailableLoyaltyPoints(),
                    'total_earned' => $profile->loyalty_points['total_earned'] ?? 0,
                    'total_used' => $profile->loyalty_points['total_used'] ?? 0,
                    'history' => array_reverse($history) // Más recientes primero
                ],
                'message' => 'Historial de puntos obtenido exitosamente'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error gestionando puntos de lealtad: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error procesando la solicitud'
            ], 500);
        }
    }

    /**
     * Crear perfil por defecto para un usuario
     */
    private function createDefaultProfile(User $user): ClientProfile
    {
        // Heredar datos básicos del usuario
        $profileData = [
            'user_id' => $user->id,
            'communication_preference' => 'app_notification',
            'notifications_enabled' => true,
            'email_notifications' => true,
            'sms_notifications' => false,
            'preferred_mechanic_radius' => 20,
            'auto_accept_quotes' => false,
            'account_type' => 'basic',
            'theme_preference' => 'auto',
            'language_preference' => 'es',
            'profile_visibility' => true,
            'show_location' => true,
            'allow_mechanic_recommendations' => true,
            // Inicializar campos numéricos
            'total_services_requested' => 0,
            'total_services_completed' => 0,
            'total_spent' => 0.00,
            'average_rating_given' => 0.00,
            'total_ratings_given' => 0,
            'loyalty_points' => [
                'available' => 0,
                'total_earned' => 0,
                'total_used' => 0,
                'history' => []
            ]
        ];

        // Si el usuario tiene ubicación, heredarla como ubicación de trabajo inicial
        if ($user->latitude && $user->longitude) {
            $profileData['work_latitude'] = $user->latitude;
            $profileData['work_longitude'] = $user->longitude;
            $profileData['work_address'] = $user->address;
            $profileData['work_city'] = $user->city;
        }

        return ClientProfile::create($profileData);
    }
}
