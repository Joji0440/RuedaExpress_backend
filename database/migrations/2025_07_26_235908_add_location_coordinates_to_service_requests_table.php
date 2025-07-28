<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            // Coordenadas de la ubicación del servicio
            $table->decimal('location_latitude', 10, 8)->nullable()->comment('Latitud de la ubicación del servicio');
            $table->decimal('location_longitude', 11, 8)->nullable()->comment('Longitud de la ubicación del servicio');
            
            // Índice para consultas geográficas
            $table->index(['location_latitude', 'location_longitude'], 'service_location_coordinates_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex('service_location_coordinates_index');
            $table->dropColumn(['location_latitude', 'location_longitude']);
        });
    }
};
