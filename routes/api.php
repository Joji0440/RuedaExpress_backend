<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\MechanicProfileController;
use App\Http\Controllers\ClientProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Api\LocationController as ApiLocationController;
use Illuminate\Support\Facades\Route;

// Endpoint de salud (público)
Route::get('/health', [HealthController::class, 'check']);

// Rutas públicas (sin autenticación)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Rutas protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // ==========================================
    // AUTENTICACIÓN Y USUARIO
    // ==========================================
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/notifications', [DashboardController::class, 'getNotifications']);
    Route::put('/notifications/{id}/read', [DashboardController::class, 'markNotificationAsRead']);
    
    // Perfil de usuario
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::get('/activity', [UserController::class, 'getActivity']);
    
    // ==========================================
    // GESTIÓN DE USUARIOS (Admin/Manager)
    // ==========================================
    Route::middleware(['role:administrador|manager'])->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/stats', [UserController::class, 'getStats']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole']);
        Route::post('/users/{user}/remove-role', [UserController::class, 'removeRole']);
        Route::patch('/admin/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus']);
    });

    // ==========================================
    // GESTIÓN DE VEHÍCULOS
    // ==========================================
    // Todos los usuarios autenticados pueden acceder (el controller maneja los permisos)
    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
    Route::post('/vehicles', [VehicleController::class, 'store']); // Solo clientes
    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);
    Route::post('/vehicles/{vehicle}/service-record', [VehicleController::class, 'addServiceRecord']);
    
    // Para mecánicos
    Route::middleware(['role:mecanico'])->group(function () {
        Route::get('/vehicles/nearby', [VehicleController::class, 'getNearbyVehicles']);
    });

    // ==========================================
    // PERFILES DE MECÁNICOS
    // ==========================================
    Route::get('/mechanics', [MechanicProfileController::class, 'index']);
    
    // Para clientes - buscar mecánicos cercanos
    Route::middleware(['role:cliente'])->group(function () {
        Route::get('/mechanics/nearby', [MechanicProfileController::class, 'getNearbyMechanics']);
    });
    
    // Solo mecánicos pueden gestionar su propio perfil
    Route::middleware(['role:mecanico'])->group(function () {
        Route::get('/mechanics/profile', [MechanicProfileController::class, 'getMyProfile']);
        Route::post('/mechanics/profile', [MechanicProfileController::class, 'store']);
        Route::put('/mechanics/profile', [MechanicProfileController::class, 'updateMyProfile']);
        Route::put('/mechanics/{mechanicProfile}', [MechanicProfileController::class, 'update']);
        Route::put('/mechanics/{mechanicProfile}/availability', [MechanicProfileController::class, 'updateAvailability']);
        Route::get('/mechanics/{mechanicProfile}/stats', [MechanicProfileController::class, 'getStats']);
    });
    
    Route::get('/mechanics/{mechanicProfile}', [MechanicProfileController::class, 'show']);

    // ==========================================
    // PERFILES DE CLIENTES
    // ==========================================
    // Solo clientes pueden gestionar su propio perfil
    Route::middleware(['role:cliente'])->group(function () {
        Route::get('/client/profile', [ClientProfileController::class, 'show']);
        Route::put('/client/profile', [ClientProfileController::class, 'update']);
        Route::get('/client/dashboard-config', [ClientProfileController::class, 'getDashboardConfig']);
        Route::put('/client/dashboard-config', [ClientProfileController::class, 'updateDashboardConfig']);
        Route::get('/client/stats', [ClientProfileController::class, 'getStats']);
        Route::post('/client/loyalty-points', [ClientProfileController::class, 'manageLoyaltyPoints']);
    });

    // ==========================================
    // SERVICIOS Y SOLICITUDES
    // ==========================================
    // Para clientes
    Route::middleware(['role:cliente', 'check.active'])->group(function () {
        Route::post('/services/request', [ServiceController::class, 'createServiceRequest']);
        Route::get('/services/find-mechanics', [ServiceController::class, 'findMechanicsBySpecialty']);
    });
    
    // Para mecánicos
    Route::middleware(['role:mecanico', 'check.active'])->group(function () {
        Route::get('/services/available-requests', [ServiceController::class, 'getAvailableRequests']);
        Route::post('/services/respond', [ServiceController::class, 'respondToRequest']);
        // Rutas específicas para service-requests de mecánicos
        Route::get('/mechanics/service-requests/available', [ServiceRequestController::class, 'getAvailableRequests']);
        Route::post('/mechanics/service-requests/{serviceRequest}/accept', [ServiceRequestController::class, 'acceptRequest']);
        Route::post('/mechanics/service-requests/{serviceRequest}/reject', [ServiceRequestController::class, 'rejectRequest']);
    });
    
    // Historial de servicios (todos los roles)
    Route::get('/services/history', [ServiceController::class, 'getServiceHistory']);

    // ==========================================
    // SOLICITUDES DE SERVICIO (SERVICE REQUESTS)
    // ==========================================
    Route::middleware(['check.active'])->group(function () {
        Route::apiResource('service-requests', ServiceRequestController::class);
        Route::patch('/service-requests/{serviceRequest}/status', [ServiceRequestController::class, 'updateStatus']);
        
        // Rutas específicas para mecánicos con cálculo de distancias
        Route::middleware(['role:mecanico'])->group(function () {
            Route::get('/service-requests/{serviceRequest}/distance', [ServiceRequestController::class, 'calculateDistanceToService']);
            Route::get('/service-requests/with-distance', [ServiceRequestController::class, 'getServicesWithDistance']);
        });
    });

    // ==========================================
    // GEOLOCALIZACIÓN Y DISTANCIAS
    // ==========================================
    Route::middleware(['check.active'])->group(function () {
        // Rutas generales de geolocalización (existentes)
        Route::put('/location', [LocationController::class, 'updateLocation']);
        Route::get('/location', [LocationController::class, 'getCurrentLocation']);
        Route::get('/location/nearby-users', [LocationController::class, 'findNearbyUsers']);
        Route::post('/location/calculate-route', [LocationController::class, 'calculateRoute']);
        Route::get('/location/search-addresses', [LocationController::class, 'searchAddresses']);
        Route::get('/location/mechanic-service-area/{mechanic}', [LocationController::class, 'getMechanicServiceArea']);
        
        // Nuevas rutas específicas para mecánicos y cálculo de distancias
        Route::middleware(['role:mecanico'])->group(function () {
            Route::put('/mechanic/location', [ApiLocationController::class, 'updateMechanicLocation']);
            Route::get('/mechanic/location', [ApiLocationController::class, 'getMechanicLocation']);
            Route::get('/mechanic/services/nearby', [ApiLocationController::class, 'getNearbyServices']);
            Route::get('/service-requests/{serviceRequest}/distance', [ApiLocationController::class, 'calculateDistanceToService']);
        });
        
        // Rutas para clientes para encontrar mecánicos cercanos
        Route::middleware(['role:cliente'])->group(function () {
            Route::get('/mechanics/nearby', [ApiLocationController::class, 'getNearbyMechanics']);
        });
    });

    // ==========================================
    // ADMINISTRACIÓN AVANZADA (Solo Admin)
    // ==========================================
    Route::middleware(['role:administrador'])->group(function () {
        // Verificación de mecánicos
        Route::put('/mechanics/{mechanicProfile}', [MechanicProfileController::class, 'update']); // Admin puede verificar
        
        // Estadísticas avanzadas
        Route::get('/admin/dashboard-stats', [AdminController::class, 'getStats']);
        Route::get('/admin/users', [AdminController::class, 'listUsers']);
        Route::get('/admin/users/{user}', [AdminController::class, 'show']);
        Route::put('/admin/users/{user}', [AdminController::class, 'update']);
        Route::delete('/admin/users/{user}', [AdminController::class, 'destroy']);
        Route::post('/admin/users/{user}/assign-role', [AdminController::class, 'assignRole']);
        Route::post('/admin/users/{user}/remove-role', [AdminController::class, 'removeRole']);
    });
});