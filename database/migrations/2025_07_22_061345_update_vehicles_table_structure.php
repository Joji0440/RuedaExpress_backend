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
        Schema::table('vehicles', function (Blueprint $table) {
            // Cambiar 'brand' por 'make'
            $table->renameColumn('brand', 'make');
            
            // Cambiar 'engine_type' por 'fuel_type' 
            $table->renameColumn('engine_type', 'fuel_type');
            
            // Cambiar 'transmission' por 'transmission_type'
            $table->renameColumn('transmission', 'transmission_type');
            
            // Cambiar 'maintenance_history' por 'service_history'
            $table->renameColumn('maintenance_history', 'service_history');
            
            // Cambiar 'next_service_mileage' por 'next_service_due'
            $table->dropColumn('next_service_mileage');
            $table->date('next_service_due')->nullable()->comment('Fecha del próximo servicio');
            
            // Agregar nuevos campos
            $table->string('insurance_company', 100)->nullable()->comment('Compañía de seguros');
            $table->string('insurance_policy_number', 50)->nullable()->comment('Número de póliza');
            $table->json('emergency_contacts')->nullable()->comment('Contactos de emergencia');
            $table->json('preferences')->nullable()->comment('Preferencias del cliente');
            
            // Remover campos no utilizados
            $table->dropColumn('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Revertir cambios
            $table->renameColumn('make', 'brand');
            $table->renameColumn('fuel_type', 'engine_type');
            $table->renameColumn('transmission_type', 'transmission');
            $table->renameColumn('service_history', 'maintenance_history');
            
            $table->dropColumn(['next_service_due', 'insurance_company', 'insurance_policy_number', 'emergency_contacts', 'preferences']);
            $table->integer('next_service_mileage')->nullable()->comment('Kilometraje del próximo servicio');
            $table->boolean('is_primary')->default(false)->comment('Vehículo principal del usuario');
        });
    }
};
