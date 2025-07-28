<?php

namespace App\Services;

trait DistanceCalculator
{
    /**
     * Calcular distancia entre dos puntos usando la fórmula de Haversine
     * 
     * @param float $lat1 Latitud del primer punto
     * @param float $lon1 Longitud del primer punto
     * @param float $lat2 Latitud del segundo punto
     * @param float $lon2 Longitud del segundo punto
     * @return float Distancia en kilómetros
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Radio de la Tierra en kilómetros

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Calcular tiempo de viaje estimado
     * 
     * @param float $distanceKm Distancia en kilómetros
     * @param float $averageSpeedKmh Velocidad promedio en km/h (por defecto 30 km/h para ciudad)
     * @return array Array con horas, minutos y formato
     */
    public function calculateTravelTime(float $distanceKm, float $averageSpeedKmh = 30): array
    {
        $timeHours = $distanceKm / $averageSpeedKmh;
        $hours = floor($timeHours);
        $minutes = round(($timeHours - $hours) * 60);

        return [
            'hours' => (int) $hours,
            'minutes' => (int) $minutes,
            'formatted' => $this->formatTravelTime($hours, $minutes),
        ];
    }

    /**
     * Validar si una distancia está dentro del radio permitido
     * 
     * @param float $distance Distancia en kilómetros
     * @param int $radiusKm Radio permitido en kilómetros
     * @return array Estado de validación
     */
    public function validateRadius(float $distance, int $radiusKm): array
    {
        $percentage = ($distance / $radiusKm) * 100;
        $withinRadius = $distance <= $radiusKm;

        // Determinar el estado basado en el porcentaje del radio
        if ($percentage <= 50) {
            $status = 'optimal';
            $message = 'Distancia óptima - Muy cerca';
        } elseif ($percentage <= 75) {
            $status = 'good';
            $message = 'Buena distancia - Cerca';
        } elseif ($percentage <= 100) {
            $status = 'limit';
            $message = 'En el límite del radio de viaje';
        } else {
            $status = 'exceeded';
            $message = 'Fuera del radio de viaje configurado';
        }

        return [
            'status' => $status,
            'within_radius' => $withinRadius,
            'percentage' => round($percentage, 1),
            'message' => $message,
        ];
    }

    /**
     * Formatear distancia para mostrar
     * 
     * @param float $km Distancia en kilómetros
     * @return string Distancia formateada
     */
    public function formatDistance(float $km): string
    {
        if ($km < 1) {
            return number_format($km * 1000, 0) . ' m';
        }
        
        return number_format($km, 1) . ' km';
    }

    /**
     * Formatear tiempo de viaje
     * 
     * @param int $hours Horas
     * @param int $minutes Minutos
     * @return string Tiempo formateado
     */
    public function formatTravelTime(int $hours, int $minutes): string
    {
        if ($hours === 0) {
            return $minutes . ' min';
        }
        
        if ($minutes === 0) {
            return $hours . 'h';
        }
        
        return $hours . 'h ' . $minutes . 'min';
    }
}
