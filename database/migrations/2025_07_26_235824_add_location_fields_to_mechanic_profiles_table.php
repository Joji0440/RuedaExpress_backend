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
        Schema::table('mechanic_profiles', function (Blueprint $table) {
            // Campos de ubicación del mecánico
            $table->decimal('latitude', 10, 8)->nullable()->comment('Latitud de la ubicación del mecánico');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Longitud de la ubicación del mecánico');
            $table->text('address')->nullable()->comment('Dirección legible de la ubicación del mecánico');
            $table->timestamp('location_updated_at')->nullable()->comment('Última actualización de la ubicación');
            
            // Índices para consultas de ubicación
            $table->index(['latitude', 'longitude'], 'location_coordinates_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mechanic_profiles', function (Blueprint $table) {
            $table->dropIndex('location_coordinates_index');
            $table->dropColumn(['latitude', 'longitude', 'address', 'location_updated_at']);
        });
    }
};
