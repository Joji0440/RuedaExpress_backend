<?php

namespace App\Traits;

trait DistanceCalculator
{
    /**
     * Calcular la distancia entre dos puntos usando la fórmula Haversine
     * Retorna la distancia en kilómetros
     * 
     * @param float $lat1 Latitud del primer punto
     * @param float $lon1 Longitud del primer punto
     * @param float $lat2 Latitud del segundo punto
     * @param float $lon2 Longitud del segundo punto
     * @return float Distancia en kilómetros
     */
    public function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        // Verificar que todas las coordenadas están presentes
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return 0;
        }

        // Radio de la Tierra en kilómetros
        $earthRadius = 6371;

        // Convertir grados a radianes
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        // Diferencias
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        // Fórmula Haversine
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Distancia en kilómetros
        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Calcular el tiempo estimado de viaje
     * 
     * @param float $distanceKm Distancia en kilómetros
     * @param float $averageSpeedKmh Velocidad promedio en km/h (por defecto 30)
     * @return array Array con horas, minutos y formato
     */
    public function calculateTravelTime($distanceKm, $averageSpeedKmh = 30): array
    {
        if ($distanceKm <= 0) {
            return [
                'hours' => 0,
                'minutes' => 0,
                'formatted' => '0 min'
            ];
        }

        $timeHours = $distanceKm / $averageSpeedKmh;
        $hours = floor($timeHours);
        $minutes = round(($timeHours - $hours) * 60);

        return [
            'hours' => (int) $hours,
            'minutes' => (int) $minutes,
            'formatted' => $this->formatTravelTime($hours * 60 + $minutes),
        ];
    }

    /**
     * Verificar si una ubicación está dentro del radio de viaje
     * 
     * @param float $distance Distancia en kilómetros
     * @param int $radiusKm Radio de viaje en kilómetros
     * @return array Estado y información de la verificación
     */
    public function validateRadius($distance, $radiusKm): array
    {
        return $this->checkWithinRadius($distance, $radiusKm);
    }

    /**
     * Verificar si una ubicación está dentro del radio de viaje
     * 
     * @param float $distance Distancia en kilómetros
     * @param int $radiusKm Radio de viaje en kilómetros
     * @return array Estado y información de la verificación
     */
    public function checkWithinRadius($distance, $radiusKm): array
    {
        $percentage = $radiusKm > 0 ? ($distance / $radiusKm) * 100 : 0;

        if ($distance <= $radiusKm) {
            if ($percentage <= 50) {
                return [
                    'within_radius' => true,
                    'status' => 'optimal',
                    'percentage' => $percentage,
                    'message' => 'Ubicación óptima'
                ];
            } elseif ($percentage <= 80) {
                return [
                    'within_radius' => true,
                    'status' => 'good',
                    'percentage' => $percentage,
                    'message' => 'Ubicación conveniente'
                ];
            } else {
                return [
                    'within_radius' => true,
                    'status' => 'limit',
                    'percentage' => $percentage,
                    'message' => 'En el límite del radio'
                ];
            }
        } else {
            return [
                'within_radius' => false,
                'status' => 'exceeded',
                'percentage' => $percentage,
                'message' => 'Fuera del radio de viaje'
            ];
        }
    }

    /**
     * Formatear la distancia para mostrar al usuario
     * 
     * @param float $distance Distancia en kilómetros
     * @return string Distancia formateada
     */
    public function formatDistance($distance): string
    {
        if ($distance < 1) {
            return round($distance * 1000) . ' m';
        } else {
            return round($distance, 1) . ' km';
        }
    }

    /**
     * Formatear el tiempo de viaje para mostrar al usuario
     * 
     * @param int $minutes Tiempo en minutos
     * @return string Tiempo formateado
     */
    public function formatTravelTime($minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        } else {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            
            if ($remainingMinutes > 0) {
                return $hours . 'h ' . $remainingMinutes . 'min';
            } else {
                return $hours . 'h';
            }
        }
    }
}
