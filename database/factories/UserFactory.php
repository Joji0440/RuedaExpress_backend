<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * El modelo asociado con esta factory.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => bcrypt('password'), // Contraseña por defecto
            'remember_token' => Str::random(10),
            
            // Nuevos campos
            'phone' => $this->faker->phoneNumber(),
            'profile_photo_path' => null,
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'latitude' => $this->faker->latitude(-34.0, -32.0), // Coordenadas de ejemplo para Argentina
            'longitude' => $this->faker->longitude(-59.0, -57.0),
            'last_location_update' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'is_active' => true,
            'last_login_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'suspended_at' => null,
        ];
    }

    /**
     * Estado para usuarios cliente
     */
    public function cliente()
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('cliente');
        });
    }

    /**
     * Estado para usuarios mecánico
     */
    public function mecanico()
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('mecanico');
            
            // Crear perfil de mecánico automáticamente
            $user->mechanicProfile()->create([
                'specializations' => $this->faker->randomElements([
                    'motor', 'transmision', 'frenos', 'suspension', 'electrico'
                ], $this->faker->numberBetween(1, 3)),
                'experience_years' => $this->faker->numberBetween(1, 20),
                'hourly_rate' => $this->faker->randomFloat(2, 15, 80),
                'travel_radius' => $this->faker->numberBetween(5, 50),
                'emergency_available' => $this->faker->boolean(30),
                'is_verified' => $this->faker->boolean(70),
                'bio' => $this->faker->paragraph(),
                'minimum_service_fee' => $this->faker->randomFloat(2, 20, 100),
            ]);
        });
    }

    /**
     * Estado para usuarios administrador
     */
    public function administrador()
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('administrador');
        });
    }

    /**
     * Estado para usuarios inactivos
     */
    public function inactive()
    {
        return $this->state([
            'is_active' => false,
            'suspended_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }
}
