<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ClientProfile;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ClientProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los usuarios con rol de cliente
        $clientRole = Role::where('name', 'cliente')->first();
        
        if (!$clientRole) {
            $this->command->warn('El rol "cliente" no existe. Ejecuta primero el RoleSeeder.');
            return;
        }

        $clients = User::role('cliente')->get();

        if ($clients->isEmpty()) {
            $this->command->warn('No hay usuarios con rol de cliente. Ejecuta primero el UserSeeder.');
            return;
        }

        foreach ($clients as $client) {
            // Verificar si ya tiene perfil
            if ($client->clientProfile) {
                continue;
            }

            // Crear perfil con datos variados
            $profileData = $this->generateClientProfileData($client);
            
            ClientProfile::create($profileData);
            
            $this->command->info("Perfil de cliente creado para: {$client->name}");
        }

        $this->command->info('Perfiles de clientes creados exitosamente.');
    }

    /**
     * Generar datos de perfil para un cliente
     */
    private function generateClientProfileData(User $client): array
    {
        $occupations = [
            'Ingeniero de Software', 'Médico', 'Abogado', 'Profesor', 'Arquitecto',
            'Contador', 'Diseñador Gráfico', 'Vendedor', 'Administrador', 'Consultor',
            'Periodista', 'Chef', 'Psicólogo', 'Farmacéutico', 'Electricista'
        ];

        $servicePreferences = [
            'preferred_time' => fake()->randomElement(['morning', 'afternoon', 'evening', 'flexible']),
            'preferred_location' => fake()->randomElement(['home', 'work', 'mechanic_shop', 'any']),
            'special_instructions' => fake()->boolean(30) ? fake()->sentence() : null,
            'preferred_contact_method' => fake()->randomElement(['phone', 'email', 'app'])
        ];

        $loyaltyPoints = [
            'available' => fake()->numberBetween(0, 500),
            'total_earned' => fake()->numberBetween(100, 2000),
            'total_used' => fake()->numberBetween(0, 1000),
            'history' => $this->generateLoyaltyHistory()
        ];

        $dashboardLayout = [
            'widgets' => [
                'recent_services' => ['enabled' => true, 'position' => 1],
                'vehicles' => ['enabled' => true, 'position' => 2],
                'quick_actions' => ['enabled' => true, 'position' => 3],
                'stats' => ['enabled' => fake()->boolean(80), 'position' => 4],
                'loyalty_points' => ['enabled' => fake()->boolean(60), 'position' => 5],
            ],
            'theme' => fake()->randomElement(['light', 'dark', 'auto']),
            'compact_mode' => fake()->boolean(30),
        ];

        return [
            'user_id' => $client->id,
            'birth_date' => fake()->boolean(70) ? fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d') : null,
            'gender' => fake()->randomElement(['male', 'female', 'other', 'prefer_not_to_say']),
            'occupation' => fake()->randomElement($occupations),
            'bio' => fake()->boolean(40) ? fake()->paragraph(2) : null,
            
            // Contacto de emergencia
            'emergency_contact_name' => fake()->boolean(80) ? fake()->name() : null,
            'emergency_contact_phone' => fake()->boolean(80) ? fake()->phoneNumber() : null,
            'emergency_contact_relationship' => fake()->boolean(80) ? fake()->randomElement(['Cónyuge', 'Padre', 'Madre', 'Hermano/a', 'Hijo/a', 'Amigo/a']) : null,
            
            // Preferencias de servicio
            'preferred_service_times' => $this->generatePreferredTimes(),
            'communication_preference' => fake()->randomElement(['phone', 'email', 'sms', 'app_notification']),
            'notifications_enabled' => fake()->boolean(90),
            'email_notifications' => fake()->boolean(80),
            'sms_notifications' => fake()->boolean(30),
            
            // Configuración de servicios
            'preferred_max_cost' => fake()->boolean(60) ? fake()->randomFloat(2, 50, 500) : null,
            'preferred_mechanic_radius' => fake()->numberBetween(10, 50),
            'service_preferences' => $servicePreferences,
            'auto_accept_quotes' => fake()->boolean(20),
            
            // Ubicación laboral (opcional)
            'work_address' => fake()->boolean(60) ? fake()->streetAddress() : null,
            'work_city' => fake()->boolean(60) ? fake()->city() : null,
            'work_latitude' => fake()->boolean(60) ? fake()->latitude() : null,
            'work_longitude' => fake()->boolean(60) ? fake()->longitude() : null,
            
            // Estadísticas simuladas
            'total_services_requested' => $servicesRequested = fake()->numberBetween(0, 25),
            'total_services_completed' => fake()->numberBetween(0, min($servicesRequested, $servicesRequested - 2)),
            'total_spent' => fake()->randomFloat(2, 0, 2500),
            'average_rating_given' => fake()->randomFloat(2, 3.0, 5.0),
            'total_ratings_given' => fake()->numberBetween(0, 20),
            
            // Configuración de cuenta
            'account_type' => fake()->randomElement(['basic', 'basic', 'basic', 'premium', 'vip']), // Más básicas
            'premium_expires_at' => fake()->boolean(10) ? fake()->dateTimeBetween('now', '+1 year') : null,
            'loyalty_points' => $loyaltyPoints,
            
            // Personalización
            'theme_preference' => fake()->randomElement(['light', 'dark', 'auto']),
            'language_preference' => 'es',
            'dashboard_layout' => $dashboardLayout,
            
            // Privacidad
            'profile_visibility' => fake()->boolean(85),
            'show_location' => fake()->boolean(90),
            'allow_mechanic_recommendations' => fake()->boolean(95),
        ];
    }

    /**
     * Generar horarios preferidos de servicio
     */
    private function generatePreferredTimes(): array
    {
        $schedule = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            if (fake()->boolean(70)) { // 70% probabilidad de tener preferencias para el día
                $slots = [];
                
                // Generar 1-2 slots de tiempo
                $numSlots = fake()->numberBetween(1, 2);
                for ($i = 0; $i < $numSlots; $i++) {
                    $startHour = fake()->numberBetween(7, 18);
                    $endHour = min($startHour + fake()->numberBetween(2, 6), 22);
                    
                    $slots[] = [
                        'start' => sprintf('%02d:00', $startHour),
                        'end' => sprintf('%02d:00', $endHour),
                        'flexible' => fake()->boolean(30)
                    ];
                }
                
                $schedule[$day] = $slots;
            }
        }
        
        return $schedule;
    }

    /**
     * Generar historial de puntos de lealtad
     */
    private function generateLoyaltyHistory(): array
    {
        $history = [];
        $numEntries = fake()->numberBetween(0, 10);
        
        for ($i = 0; $i < $numEntries; $i++) {
            $isEarned = fake()->boolean(70);
            $points = $isEarned ? fake()->numberBetween(5, 50) : -fake()->numberBetween(10, 100);
            
            $reasons = $isEarned 
                ? ['Servicio completado', 'Referido exitoso', 'Bono de registro', 'Promoción especial']
                : ['Descuento aplicado', 'Canje de premio', 'Descuento en servicio'];
            
            $history[] = [
                'points' => $points,
                'reason' => fake()->randomElement($reasons),
                'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
                'type' => $isEarned ? 'earned' : 'used'
            ];
        }
        
        // Ordenar por fecha (más recientes primero)
        usort($history, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $history;
    }
}
