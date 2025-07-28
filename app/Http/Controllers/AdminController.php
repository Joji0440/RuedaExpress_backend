<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    /**
     * Listar todos los usuarios (solo admin)
     */
    public function listUsers(Request $request)
    {
        try {
            // Obtener parámetros de paginación y filtros
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $role = $request->get('role');

            $query = User::with('roles');

            // Aplicar filtro de búsqueda
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Aplicar filtro de rol
            if ($role) {
                $query->role($role);
            }

            // Ordenar por fecha de creación (más recientes primero)
            $query->orderBy('created_at', 'desc');

            // Paginar resultados
            $users = $query->paginate($perPage);

            return response()->json([
                'message' => 'Usuarios obtenidos exitosamente',
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'total_pages' => $users->lastPage(),
                    'total_items' => $users->total(),
                    'per_page' => $users->perPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un usuario específico
     */
    public function show(User $user)
    {
        return response()->json([
            'message' => 'Usuario obtenido exitosamente',
            'data' => $user->load('roles')
        ]);
    }

    /**
     * Actualizar un usuario
     */
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:8|confirmed',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Actualizar datos básicos
        $data = $request->only(['name', 'email']);
        
        if ($request->has('password') && $request->password) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        // Actualizar roles si se proporcionan
        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'data' => $user->fresh()->load('roles')
        ]);
    }

    /**
     * Eliminar un usuario
     */
    public function destroy(User $user)
    {
        // Prevenir que el admin se elimine a sí mismo
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta'
            ], 403);
        }

        // Prevenir eliminar el último admin
        if ($user->hasRole('administrador')) {
            $adminCount = User::role('administrador')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'No se puede eliminar el último administrador'
                ], 403);
            }
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Asignar rol a un usuario
     */
    public function assignRole(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Rol asignado exitosamente',
            'data' => $user->fresh()->load('roles')
        ]);
    }

    /**
     * Remover rol de un usuario
     */
    public function removeRole(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevenir remover rol admin del último admin
        if ($request->role === 'administrador' && $user->hasRole('administrador')) {
            $adminCount = User::role('administrador')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'No se puede remover el rol del último administrador'
                ], 403);
            }
        }

        $user->removeRole($request->role);

        return response()->json([
            'message' => 'Rol removido exitosamente',
            'data' => $user->fresh()->load('roles')
        ]);
    }

    /**
     * Obtener estadísticas del sistema
     */
    public function getStats()
    {
        // Estadísticas básicas de usuarios
        $totalUsers = User::count();
        $adminUsers = User::role('administrador')->count();
        $mechanicUsers = User::role('mecanico')->count();
        $clientUsers = User::role('cliente')->count();
        
        // Usuarios activos (pueden modificar esta lógica según sus necesidades)
        $activeUsers = User::where('is_active', true)->count();
        
        // Usuarios del mes actual
        $usersThisMonth = User::whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)
                            ->count();
        
        // Usuarios de la semana actual
        $usersThisWeek = User::whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->count();

        // Usuarios registrados hoy
        $usersToday = User::whereDate('created_at', today())->count();

        // Usuarios recientes (últimos 5)
        $recentUsers = User::latest()
                          ->take(5)
                          ->with('roles')
                          ->get(['id', 'name', 'email', 'created_at']);

        // Distribución de roles en porcentajes
        $roleDistribution = [
            'administradores' => $totalUsers > 0 ? round(($adminUsers / $totalUsers) * 100, 1) : 0,
            'mecanicos' => $totalUsers > 0 ? round(($mechanicUsers / $totalUsers) * 100, 1) : 0,
            'clientes' => $totalUsers > 0 ? round(($clientUsers / $totalUsers) * 100, 1) : 0,
        ];

        // Estadísticas de crecimiento (comparación con mes anterior)
        $lastMonth = now()->subMonth();
        $usersLastMonth = User::whereMonth('created_at', $lastMonth->month)
                            ->whereYear('created_at', $lastMonth->year)
                            ->count();
        
        $growthPercentage = $usersLastMonth > 0 
            ? round((($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100, 1)
            : 0;

        $stats = [
            // Contadores principales
            'total_users' => $totalUsers,
            'admin_users' => $adminUsers,
            'mechanic_users' => $mechanicUsers,
            'client_users' => $clientUsers,
            'active_users' => $activeUsers,
            
            // Estadísticas temporales
            'users_today' => $usersToday,
            'users_this_week' => $usersThisWeek,
            'users_this_month' => $usersThisMonth,
            'users_last_month' => $usersLastMonth,
            
            // Análisis de crecimiento
            'growth_percentage' => $growthPercentage,
            'is_growing' => $growthPercentage > 0,
            
            // Distribución de roles
            'role_distribution' => $roleDistribution,
            
            // Datos adicionales
            'recent_users' => $recentUsers,
            
            // Estadísticas del sistema (futuro)
            'total_vehicles' => 0, // Placeholder para cuando implementes vehículos
            'total_services' => 0, // Placeholder para cuando implementes servicios
            'active_services' => 0, // Placeholder para servicios activos
            'completed_services' => 0, // Placeholder para servicios completados
            'monthly_revenue' => 0, // Placeholder para ingresos mensuales
        ];

        return response()->json([
            'message' => 'Estadísticas obtenidas exitosamente',
            'data' => $stats
        ]);
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Crear el usuario
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            // Asignar roles si se proporcionan, sino asignar rol 'cliente' por defecto
            if ($request->has('roles') && !empty($request->roles)) {
                $user->assignRole($request->roles);
            } else {
                $user->assignRole('cliente');
            }

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'data' => $user->fresh()->load('roles')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar información básica del usuario (solo admin)
     */
    public function updateUserInfo(Request $request, User $user)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            return response()->json([
                'message' => 'Información del usuario actualizada exitosamente',
                'data' => $user->fresh()->load('roles')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar/desactivar usuario (solo manager o administrador)
     */
    public function toggleUserStatus(Request $request, User $user)
    {
        try {
            $currentUser = $request->user();
            
            // Verificar que el usuario actual sea administrador
            if (!$currentUser->hasRole('administrador')) {
                return response()->json([
                    'message' => 'No tienes permisos para cambiar el estado de usuarios'
                ], 403);
            }

            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'is_active' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // No permitir desactivar al propio usuario
            if ($user->id === $currentUser->id) {
                return response()->json([
                    'message' => 'No puedes cambiar tu propio estado'
                ], 400);
            }

            // Verificar si la tabla users tiene la columna is_active
            if (!$user->getConnection()->getSchemaBuilder()->hasColumn('users', 'is_active')) {
                return response()->json([
                    'message' => 'La funcionalidad de activación/desactivación no está disponible'
                ], 501);
            }

            // Actualizar estado del usuario
            $user->is_active = $request->is_active;
            $user->save();

            $status = $request->is_active ? 'activado' : 'desactivado';

            return response()->json([
                'message' => "Usuario {$status} exitosamente",
                'data' => $user->fresh()->load('roles')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el estado del usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
