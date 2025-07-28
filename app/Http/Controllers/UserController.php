<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\DTO\UserDTO;
use App\Services\UserService;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Obtener lista de usuarios (para admin y manager)
     */
    public function index(): JsonResponse
    {
        try {
            $users = $this->userService->getAllUsers();

            return response()->json([
                'message' => 'Usuarios obtenidos exitosamente',
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas (para admin y manager)
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->userService->getUserStats();

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
     * Crear usuario (solo manager)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $currentUser = $request->user();
            
            // Solo managers pueden crear usuarios
            if (!$currentUser->hasRole('manager')) {
                return response()->json([
                    'message' => 'No tienes permisos para crear usuarios'
                ], 403);
            }

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'role' => 'required|string|exists:roles,name',
            ]);

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => bcrypt($validatedData['password']),
            ]);

            // Asignar el rol especificado
            $user->assignRole($validatedData['role']);

            return response()->json([
                'message' => 'Usuario creado correctamente',
                'data' => new UserResource($user->load('roles'))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Actualizar usuario (admin solo puede editar info básica, manager puede hacer más)
     */
    public function update(Request $request, User $user): JsonResponse
    {
        try {
            $currentUser = $request->user();
            
            // Validar datos básicos
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            ]);

            // Si es admin, solo puede editar información básica
            if ($currentUser->hasRole('admin') && !$currentUser->hasRole('manager')) {
                $user->update([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                ]);
                
                return response()->json([
                    'message' => 'Información del usuario actualizada exitosamente',
                    'data' => new UserResource($user->fresh()->load('roles'))
                ]);
            }
            
            // Si es manager, puede hacer más actualizaciones si es necesario
            if ($currentUser->hasRole('manager')) {
                $user->update([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                ]);
                
                return response()->json([
                    'message' => 'Usuario actualizado exitosamente',
                    'data' => new UserResource($user->fresh()->load('roles'))
                ]);
            }
            
            // Si no tiene permisos
            return response()->json([
                'message' => 'No tienes permisos para actualizar usuarios'
            ], 403);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 422);
        }
    }
    public function assignRole(Request $request, User $user): JsonResponse
    {
        try {
            $currentUser = $request->user();
            
            // Solo managers pueden asignar roles
            if (!$currentUser->hasRole('manager')) {
                return response()->json([
                    'message' => 'No tienes permisos para asignar roles'
                ], 403);
            }

            $validatedData = $request->validate([
                'role' => 'required|string|exists:roles,name',
            ]);

            $updatedUser = $this->userService->assignRole($user, $validatedData['role']);

            return response()->json([
                'message' => 'Rol asignado correctamente',
                'data' => new UserResource($updatedUser->load('roles'))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al asignar rol',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remover rol de un usuario (solo manager)
     */
    public function removeRole(Request $request, User $user): JsonResponse
    {
        try {
            $currentUser = $request->user();
            
            // Solo managers pueden remover roles
            if (!$currentUser->hasRole('manager')) {
                return response()->json([
                    'message' => 'No tienes permisos para remover roles'
                ], 403);
            }

            $validatedData = $request->validate([
                'role' => 'required|string|exists:roles,name',
            ]);

            $user->removeRole($validatedData['role']);

            return response()->json([
                'message' => 'Rol removido correctamente',
                'data' => new UserResource($user->fresh()->load('roles'))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al remover rol',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Eliminar usuario (solo manager puede eliminar cualquier usuario)
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        try {
            $currentUser = $request->user();
            
            // Solo managers pueden eliminar usuarios
            if (!$currentUser->hasRole('manager')) {
                return response()->json([
                    'message' => 'No tienes permisos para eliminar usuarios'
                ], 403);
            }

            // Verificar que el manager no se elimine a sí mismo
            if ($user->id === $currentUser->id) {
                return response()->json([
                    'message' => 'No puedes eliminarte a ti mismo'
                ], 403);
            }

            $this->userService->deleteUser($user);

            return response()->json([
                'message' => 'Usuario eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
