<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Iniciando seeding de la base de datos...');

        // 1. Actualizar roles del sistema
        $this->call(UpdateSystemRolesSeeder::class);
        
        // 2. Verificar si estamos en entorno de desarrollo
        if (app()->environment(['local', 'development'])) {
            $this->command->info('🛠️ Entorno de desarrollo detectado - Creando datos de prueba...');
            $this->call(DevelopmentDataSeeder::class);
        } else {
            // Solo crear datos mínimos en producción
            $this->createProductionData();
        }

        $this->command->info('✅ Seeding completado!');
    }

    /**
     * Crear datos mínimos para producción
     */
    private function createProductionData(): void
    {
        $this->command->info('🏭 Entorno de producción - Creando solo datos esenciales...');
        
        // Solo crear admin principal si no existe
        $admin = User::firstOrCreate(
            ['email' => 'admin@mecanica.com'],
            [
                'name' => 'Administrador Principal',
                'password' => \Hash::make('admin123'),
                'phone' => '+1234567890',
                'city' => 'Ciudad Principal',
                'is_active' => true,
                'email_verified_at' => now()
            ]
        );
        
        if (!$admin->hasRole('administrador')) {
            $admin->assignRole('administrador');
        }

        $this->command->info('👑 Administrador principal creado/verificado');
    }
}
