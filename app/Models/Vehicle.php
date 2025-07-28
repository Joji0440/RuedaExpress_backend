<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'make',           // Cambiado de 'brand'
        'model',
        'year',
        'color',
        'license_plate',
        'vin',
        'engine_size',
        'fuel_type',      // Nuevo campo
        'transmission_type', // Cambiado de 'transmission'
        'mileage',
        'last_service_date',
        'next_service_due', // Nuevo campo
        'insurance_company', // Nuevo campo
        'insurance_policy_number', // Nuevo campo
        'is_active',
        'notes',
        'service_history', // Nuevo campo
        'emergency_contacts', // Nuevo campo
        'preferences', // Nuevo campo
    ];

    protected $casts = [
        'service_history' => 'array',
        'emergency_contacts' => 'array',
        'preferences' => 'array',
        'last_service_date' => 'date',
        'next_service_due' => 'date',
        'is_active' => 'boolean',
        'year' => 'integer',
        'mileage' => 'integer',
    ];

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Relación con el usuario propietario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================
    // MÉTODOS DE UTILIDAD
    // ==========================================

    /**
     * Tipos de motor disponibles
     */
    public static function getEngineTypes(): array
    {
        return [
            'gasoline' => 'Gasolina',
            'diesel' => 'Diésel',
            'hybrid' => 'Híbrido',
            'electric' => 'Eléctrico',
            'other' => 'Otro',
        ];
    }

    /**
     * Tipos de transmisión disponibles
     */
    public static function getTransmissionTypes(): array
    {
        return [
            'manual' => 'Manual',
            'automatic' => 'Automática',
            'cvt' => 'CVT',
            'other' => 'Otra',
        ];
    }

    /**
     * Marcas de vehículos más comunes
     */
    public static function getCommonBrands(): array
    {
        return [
            'Toyota', 'Honda', 'Ford', 'Chevrolet', 'Nissan',
            'Hyundai', 'Kia', 'Mazda', 'Subaru', 'Mitsubishi',
            'Volkswagen', 'BMW', 'Mercedes-Benz', 'Audi', 'Lexus',
            'Jeep', 'Ram', 'GMC', 'Buick', 'Cadillac',
            'Infiniti', 'Acura', 'Volvo', 'Peugeot', 'Renault',
        ];
    }

    /**
     * Obtener el nombre completo del vehículo
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->make} {$this->model} {$this->year}";
    }

    /**
     * Obtener el tipo de combustible formateado
     */
    public function getFormattedFuelTypeAttribute(): string
    {
        $types = self::getEngineTypes();
        return $types[$this->fuel_type] ?? $this->fuel_type;
    }

    /**
     * Obtener el tipo de transmisión formateado
     */
    public function getFormattedTransmissionAttribute(): string
    {
        $types = self::getTransmissionTypes();
        return $types[$this->transmission_type] ?? $this->transmission_type;
    }

    /**
     * Calcular la edad del vehículo
     */
    public function getAgeAttribute(): int
    {
        return now()->year - $this->year;
    }

    /**
     * Verificar si el vehículo necesita servicio
     */
    public function needsService(): bool
    {
        if ($this->last_service_date) {
            return $this->last_service_date->diffInMonths(now()) >= 6;
        }
        
        return false;
    }

    /**
     * Agregar entrada al historial de servicio
     */
    public function addServiceRecord(array $record): void
    {
        $history = $this->service_history ?? [];
        $history[] = array_merge($record, [
            'date' => now()->toDateString(),
            'created_at' => now()->toISOString(),
        ]);
        
        $this->update(['service_history' => $history]);
    }

    /**
     * Obtener el último registro de servicio
     */
    public function getLastServiceAttribute(): ?array
    {
        $history = $this->service_history;
        
        if (empty($history)) {
            return null;
        }
        
        return end($history);
    }

    /**
     * Obtener información de identificación
     */
    public function getIdentificationAttribute(): string
    {
        if ($this->license_plate) {
            return "Placa: {$this->license_plate}";
        }
        
        if ($this->vin) {
            return "VIN: " . substr($this->vin, -8);
        }
        
        return "ID: {$this->id}";
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope para vehículos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar por marca
     */
    public function scopeByMake($query, string $make)
    {
        return $query->where('make', 'ILIKE', "%{$make}%");
    }

    /**
     * Scope para filtrar por año
     */
    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope para vehículos que necesitan servicio
     */
    public function scopeNeedsService($query)
    {
        return $query->where(function ($q) {
            $q->whereRaw('last_service_date <= ?', [now()->subMonths(6)->toDateString()]);
        });
    }

    /**
     * Scope para filtrar por tipo de combustible
     */
    public function scopeByFuelType($query, string $fuelType)
    {
        return $query->where('fuel_type', $fuelType);
    }
}
