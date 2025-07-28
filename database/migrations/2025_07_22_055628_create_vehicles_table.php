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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Información básica del vehículo
            $table->string('brand', 50)->comment('Marca del vehículo');
            $table->string('model', 100)->comment('Modelo del vehículo');
            $table->integer('year')->comment('Año del vehículo');
            
            // Especificaciones técnicas
            $table->enum('engine_type', ['gasoline', 'diesel', 'hybrid', 'electric', 'other'])
                  ->default('gasoline')
                  ->comment('Tipo de motor');
            $table->enum('transmission', ['manual', 'automatic', 'cvt', 'other'])
                  ->default('manual')
                  ->comment('Tipo de transmisión');
            $table->string('engine_size', 20)->nullable()->comment('Cilindraje del motor');
            
            // Información del estado
            $table->integer('mileage')->nullable()->comment('Kilometraje actual');
            $table->string('license_plate', 20)->nullable();
            $table->string('color', 30)->nullable();
            $table->string('vin', 50)->nullable()->comment('Número de serie del vehículo');
            
            // Información adicional
            $table->text('notes')->nullable()->comment('Notas adicionales del propietario');
            $table->json('maintenance_history')->nullable()->comment('Historial de mantenimientos');
            $table->date('last_service_date')->nullable()->comment('Fecha del último servicio');
            $table->integer('next_service_mileage')->nullable()->comment('Kilometraje del próximo servicio');
            
            // Estado del registro
            $table->boolean('is_active')->default(true)->comment('Vehículo activo');
            $table->boolean('is_primary')->default(false)->comment('Vehículo principal del usuario');
            
            $table->timestamps();
            
            // Índices
            $table->index('user_id');
            $table->index(['brand', 'model', 'year']);
            $table->index('is_active');
            $table->index(['user_id', 'is_primary']);
            $table->unique('vin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
