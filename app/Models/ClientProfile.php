<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'birth_date',
        'gender',
        'occupation',
        'bio',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'preferred_service_times',
        'communication_preference',
        'notifications_enabled',
        'email_notifications',
        'sms_notifications',
        'preferred_max_cost',
        'preferred_mechanic_radius',
        'service_preferences',
        'auto_accept_quotes',
        'work_address',
        'work_city',
        'work_latitude',
        'work_longitude',
        'total_services_requested',
        'total_services_completed',
        'total_spent',
        'average_rating_given',
        'total_ratings_given',
        'account_type',
        'premium_expires_at',
        'loyalty_points',
        'theme_preference',
        'language_preference',
        'dashboard_layout',
        'profile_visibility',
        'show_location',
        'allow_mechanic_recommendations',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'preferred_service_times' => 'array',
        'service_preferences' => 'array',
        'loyalty_points' => 'array',
        'dashboard_layout' => 'array',
        'notifications_enabled' => 'boolean',
        'email_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
        'auto_accept_quotes' => 'boolean',
        'profile_visibility' => 'boolean',
        'show_location' => 'boolean',
        'allow_mechanic_recommendations' => 'boolean',
        'preferred_max_cost' => 'decimal:2',
        'work_latitude' => 'decimal:8',
        'work_longitude' => 'decimal:8',
        'total_spent' => 'decimal:2',
        'average_rating_given' => 'decimal:2',
        'premium_expires_at' => 'datetime',
    ];

    protected $appends = [
        'age',
        'is_premium',
        'formatted_total_spent',
        'service_completion_rate',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con los vehículos del cliente
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'user_id', 'user_id');
    }

    /**
     * Relación con las solicitudes de servicio del cliente
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'client_id', 'user_id');
    }

    /**
     * Calcular la edad basada en la fecha de nacimiento
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        return $this->birth_date->diffInYears(now());
    }

    /**
     * Verificar si el cliente tiene cuenta premium activa
     */
    public function getIsPremiumAttribute(): bool
    {
        if ($this->account_type === 'basic') {
            return false;
        }

        if ($this->account_type === 'vip') {
            return true;
        }

        // Para cuentas premium, verificar fecha de expiración
        if ($this->account_type === 'premium') {
            return $this->premium_expires_at && $this->premium_expires_at->isFuture();
        }

        return false;
    }

    /**
     * Formatear el total gastado
     */
    public function getFormattedTotalSpentAttribute(): string
    {
        return '$' . number_format($this->total_spent, 2);
    }

    /**
     * Calcular tasa de finalización de servicios
     */
    public function getServiceCompletionRateAttribute(): float
    {
        if ($this->total_services_requested === 0) {
            return 0;
        }

        return ($this->total_services_completed / $this->total_services_requested) * 100;
    }

    /**
     * Obtener promedio de calificaciones dadas con valor por defecto
     */
    public function getAverageRatingGivenAttribute(): float
    {
        return (float) ($this->attributes['average_rating_given'] ?? 0);
    }

    /**
     * Obtener total de calificaciones dadas con valor por defecto
     */
    public function getTotalRatingsGivenAttribute(): int
    {
        return (int) ($this->attributes['total_ratings_given'] ?? 0);
    }

    /**
     * Obtener total de servicios solicitados con valor por defecto
     */
    public function getTotalServicesRequestedAttribute(): int
    {
        return (int) ($this->attributes['total_services_requested'] ?? 0);
    }

    /**
     * Obtener total de servicios completados con valor por defecto
     */
    public function getTotalServicesCompletedAttribute(): int
    {
        return (int) ($this->attributes['total_services_completed'] ?? 0);
    }

    /**
     * Calcular estadísticas reales desde las solicitudes de servicio
     */
    public function calculateRealStats(): array
    {
        try {
            // Usar la relación existente para obtener estadísticas
            $totalRequested = $this->serviceRequests()->count();
            
            $totalCompleted = $this->serviceRequests()
                ->where('status', 'completed')
                ->count();
            
            // Incluir también servicios con estado 'finished' por si acaso
            $totalCompletedAlternative = $this->serviceRequests()
                ->whereIn('status', ['completed', 'finished'])
                ->count();
            
            // Usar el mayor de los dos conteos
            $totalCompleted = max($totalCompleted, $totalCompletedAlternative);
            
            // Calcular total gastado
            $totalSpent = $this->serviceRequests()
                ->whereIn('status', ['completed', 'finished'])
                ->whereNotNull('final_cost')
                ->sum('final_cost') ?? 0;
            
            // Calcular tasa de finalización
            $completionRate = $totalRequested > 0 ? ($totalCompleted / $totalRequested) * 100 : 0;
            
            // Por ahora mantener las calificaciones de los campos almacenados
            $avgRatingGiven = $this->average_rating_given ?? 0;
            $totalRatingsGiven = $this->total_ratings_given ?? 0;

            return [
                'total_services_requested' => $totalRequested,
                'total_services_completed' => $totalCompleted,
                'total_spent' => (float) $totalSpent,
                'formatted_total_spent' => '$' . number_format($totalSpent, 2),
                'service_completion_rate' => round($completionRate, 1),
                'average_rating_given' => (float) $avgRatingGiven,
                'total_ratings_given' => (int) $totalRatingsGiven,
            ];
        } catch (\Exception $e) {
            // En caso de error, devolver valores por defecto
            return [
                'total_services_requested' => $this->total_services_requested ?? 0,
                'total_services_completed' => $this->total_services_completed ?? 0,
                'total_spent' => $this->total_spent ?? 0,
                'formatted_total_spent' => '$' . number_format($this->total_spent ?? 0, 2),
                'service_completion_rate' => $this->service_completion_rate ?? 0,
                'average_rating_given' => $this->average_rating_given ?? 0,
                'total_ratings_given' => $this->total_ratings_given ?? 0,
            ];
        }
    }

    /**
     * Obtener preferencias de horario formateadas
     */
    public function getFormattedServiceTimesAttribute(): array
    {
        if (!$this->preferred_service_times) {
            return [];
        }

        $times = [];
        foreach ($this->preferred_service_times as $day => $timeSlots) {
            $times[$day] = [
                'day_name' => ucfirst($day),
                'slots' => $timeSlots,
                'available' => !empty($timeSlots)
            ];
        }

        return $times;
    }

    /**
     * Verificar si el cliente permite servicios en un horario específico
     */
    public function isAvailableAt(string $day, string $time): bool
    {
        if (!$this->preferred_service_times || !isset($this->preferred_service_times[$day])) {
            return true; // Si no hay preferencias, asumir disponibilidad
        }

        $daySlots = $this->preferred_service_times[$day];
        
        foreach ($daySlots as $slot) {
            $startTime = strtotime($slot['start']);
            $endTime = strtotime($slot['end']);
            $checkTime = strtotime($time);

            if ($checkTime >= $startTime && $checkTime <= $endTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener puntos de lealtad disponibles
     */
    public function getAvailableLoyaltyPoints(): int
    {
        if (!$this->loyalty_points || !isset($this->loyalty_points['available'])) {
            return 0;
        }

        return (int) $this->loyalty_points['available'];
    }

    /**
     * Agregar puntos de lealtad
     */
    public function addLoyaltyPoints(int $points, string $reason = ''): void
    {
        $currentPoints = $this->loyalty_points ?? [];
        
        $currentPoints['available'] = ($currentPoints['available'] ?? 0) + $points;
        $currentPoints['total_earned'] = ($currentPoints['total_earned'] ?? 0) + $points;
        
        // Agregar al historial
        $currentPoints['history'][] = [
            'points' => $points,
            'reason' => $reason,
            'date' => now()->toDateTimeString(),
            'type' => 'earned'
        ];

        $this->loyalty_points = $currentPoints;
        $this->save();
    }

    /**
     * Usar puntos de lealtad
     */
    public function useLoyaltyPoints(int $points, string $reason = ''): bool
    {
        $availablePoints = $this->getAvailableLoyaltyPoints();
        
        if ($points > $availablePoints) {
            return false;
        }

        $currentPoints = $this->loyalty_points ?? [];
        $currentPoints['available'] = $availablePoints - $points;
        $currentPoints['total_used'] = ($currentPoints['total_used'] ?? 0) + $points;
        
        // Agregar al historial
        $currentPoints['history'][] = [
            'points' => -$points,
            'reason' => $reason,
            'date' => now()->toDateTimeString(),
            'type' => 'used'
        ];

        $this->loyalty_points = $currentPoints;
        $this->save();

        return true;
    }

    /**
     * Scope para clientes premium
     */
    public function scopePremium($query)
    {
        return $query->where(function ($q) {
            $q->where('account_type', 'vip')
              ->orWhere(function ($q2) {
                  $q2->where('account_type', 'premium')
                     ->where('premium_expires_at', '>', now());
              });
        });
    }

    /**
     * Scope para clientes activos
     */
    public function scopeActive($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * Actualizar estadísticas después de un servicio
     */
    public function updateServiceStats(float $cost, bool $completed = true): void
    {
        $this->increment('total_services_requested');
        
        if ($completed) {
            $this->increment('total_services_completed');
            $this->increment('total_spent', $cost);
            
            // Agregar puntos de lealtad (1 punto por cada $10 gastados)
            $pointsEarned = floor($cost / 10);
            if ($pointsEarned > 0) {
                $this->addLoyaltyPoints($pointsEarned, "Servicio completado - $" . number_format($cost, 2));
            }
        }
    }

    /**
     * Verificar si el cliente puede acceder a funciones premium
     */
    public function canAccessPremiumFeatures(): bool
    {
        return $this->is_premium;
    }

    /**
     * Obtener configuración del dashboard personalizado
     */
    public function getDashboardConfig(): array
    {
        $defaultConfig = [
            'widgets' => [
                'recent_services' => ['enabled' => true, 'position' => 1],
                'vehicles' => ['enabled' => true, 'position' => 2],
                'quick_actions' => ['enabled' => true, 'position' => 3],
                'stats' => ['enabled' => true, 'position' => 4],
                'loyalty_points' => ['enabled' => $this->is_premium, 'position' => 5],
            ],
            'theme' => $this->theme_preference ?? 'auto',
            'language' => $this->language_preference ?? 'es',
        ];

        if ($this->dashboard_layout) {
            return array_merge($defaultConfig, $this->dashboard_layout);
        }

        return $defaultConfig;
    }
}
