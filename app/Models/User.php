<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasRoles, HasApiTokens;

    protected $guard_name = 'web';

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'name', 
        'email', 
        'password',
        'phone',
        'profile_photo_path',
        'address',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        'last_location_update',
        'is_active',
        'last_login_at',
        'suspended_at',
    ];

    /**
     * Los atributos que deben ser ocultados para serialización.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Los atributos que deben ser convertidos.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_location_update' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Relación con el perfil de mecánico
     */
    public function mechanicProfile(): HasOne
    {
        return $this->hasOne(MechanicProfile::class);
    }

    /**
     * Relación con el perfil de cliente
     */
    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    /**
     * Relación con los vehículos del usuario
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Vehículo principal del usuario
     */
    public function primaryVehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class)->where('is_primary', true);
    }

    // ==========================================
    // MÉTODOS DE UTILIDAD
    // ==========================================

    /**
     * Verificar si el usuario es un cliente
     */
    public function isCliente(): bool
    {
        return $this->hasRole('cliente');
    }

    /**
     * Verificar si el usuario es un mecánico
     */
    public function isMecanico(): bool
    {
        return $this->hasRole('mecanico');
    }

    /**
     * Verificar si el usuario es un administrador
     */
    public function isAdministrador(): bool
    {
        return $this->hasRole('administrador');
    }

    /**
     * Verificar si el mecánico está verificado
     */
    public function isMecanicoVerificado(): bool
    {
        return $this->isMecanico() && 
               $this->mechanicProfile && 
               $this->mechanicProfile->is_verified;
    }

    /**
     * Obtener el nombre completo con información adicional
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->name;
        if ($this->isMecanico() && $this->mechanicProfile) {
            $name .= ' (Mecánico)';
            if ($this->mechanicProfile->is_verified) {
                $name .= ' ✓';
            }
        }
        return $name;
    }

    /**
     * Obtener la dirección completa formateada
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Verificar si el usuario tiene ubicación configurada
     */
    public function hasLocation(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Actualizar la última actividad de login
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para usuarios por rol
     */
    public function scopeByRole($query, string $role)
    {
        return $query->whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        });
    }

    /**
     * Scope para usuarios en una ciudad específica
     */
    public function scopeInCity($query, string $city)
    {
        return $query->where('city', 'ILIKE', "%{$city}%");
    }
}
