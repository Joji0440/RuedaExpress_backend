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
        Schema::create('mechanic_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Información profesional
            $table->json('specializations')->nullable()->comment('Especialidades del mecánico');
            $table->integer('experience_years')->default(0);
            $table->decimal('hourly_rate', 8, 2)->nullable()->comment('Tarifa por hora en USD');
            $table->integer('travel_radius')->default(10)->comment('Radio de desplazamiento en km');
            
            // Disponibilidad y horarios
            $table->json('availability_schedule')->nullable()->comment('Horarios de disponibilidad');
            $table->boolean('emergency_available')->default(false)->comment('Disponible para emergencias');
            
            // Verificación y estadísticas
            $table->boolean('is_verified')->default(false)->comment('Verificado por administrador');
            $table->decimal('rating_average', 3, 2)->default(0.00)->comment('Promedio de calificaciones');
            $table->integer('total_jobs')->default(0)->comment('Total de trabajos completados');
            $table->integer('total_reviews')->default(0)->comment('Total de reseñas recibidas');
            
            // Información adicional
            $table->text('bio')->nullable()->comment('Descripción del mecánico');
            $table->json('certifications')->nullable()->comment('Certificaciones y cursos');
            $table->json('tools_owned')->nullable()->comment('Herramientas disponibles');
            
            // Configuración de servicio
            $table->decimal('minimum_service_fee', 8, 2)->nullable()->comment('Tarifa mínima de servicio');
            $table->boolean('accepts_weekend_jobs')->default(true);
            $table->boolean('accepts_night_jobs')->default(false);
            
            $table->timestamps();
            
            // Índices
            $table->unique('user_id');
            $table->index('is_verified');
            $table->index('emergency_available');
            $table->index(['rating_average', 'total_jobs']);
            $table->index('travel_radius');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mechanic_profiles');
    }
};
