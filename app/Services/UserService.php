<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Obtener todos los usuarios
     */
    public function getAllUsers()
    {
        return User::with('roles')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function getUserStats()
    {
        return [
            'total_users' => User::count(),
            'admin_users' => User::role('admin')->count(),
            'manager_users' => User::role('manager')->count(),
            'regular_users' => User::role('user')->count(),
            'users_this_month' => User::whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year)
                                    ->count(),
            'recent_users' => User::latest()->take(5)->with('roles')->get()
        ];
    }

    /**
     * Crear un nuevo usuario
     */
    public function createUser(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        } else {
            $user->assignRole('user');
        }

        return $user->load('roles');
    }

    /**
     * Actualizar usuario
     */
    public function updateUser(User $user, array $data)
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return $user->fresh()->load('roles');
    }

    /**
     * Asignar rol a usuario
     */
    public function assignRole(User $user, string $role)
    {
        $user->assignRole($role);
        return $user->fresh()->load('roles');
    }

    /**
     * Eliminar usuario
     */
    public function deleteUser(User $user)
    {
        $user->delete();
        return true;
    }
}
