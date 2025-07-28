<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tablas existentes (comentado para testing con datos existentes)
        // \DB::table('role_has_permissions')->truncate();
        // \DB::table('model_has_permissions')->truncate();
        // \DB::table('model_has_roles')->truncate();
        // Role::truncate();
        // Permission::truncate();

        // Crear permisos
        $permissions = [
            'create-user',
            'read-user',
            'update-user',
            'delete-user',
            'manage-roles',
            'manage-permissions'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Crear roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Asignar permisos a roles
        $adminRole->givePermissionTo(['create-user', 'read-user', 'update-user', 'delete-user', 'manage-roles', 'manage-permissions']);
        $managerRole->givePermissionTo(['create-user', 'read-user', 'update-user', 'manage-roles']);
        $userRole->givePermissionTo(['read-user']);
    }
}
