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
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Información personal adicional
            $table->date('birth_date')->nullable()->comment('Fecha de nacimiento');
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable()->comment('Género');
            $table->string('occupation', 100)->nullable()->comment('Ocupación');
            $table->text('bio')->nullable()->comment('Descripción personal');
            
            // Información de contacto adicional
            $table->string('emergency_contact_name', 100)->nullable()->comment('Nombre del contacto de emergencia');
            $table->string('emergency_contact_phone', 20)->nullable()->comment('Teléfono del contacto de emergencia');
            $table->string('emergency_contact_relationship', 50)->nullable()->comment('Relación con el contacto de emergencia');
            
            // Preferencias de servicio
            $table->json('preferred_service_times')->nullable()->comment('Horarios preferidos para servicios');
            $table->enum('communication_preference', ['phone', 'email', 'sms', 'app_notification'])
                  ->default('app_notification')
                  ->comment('Preferencia de comunicación');
            $table->boolean('notifications_enabled')->default(true)->comment('Notificaciones habilitadas');
            $table->boolean('email_notifications')->default(true)->comment('Notificaciones por email');
            $table->boolean('sms_notifications')->default(false)->comment('Notificaciones por SMS');
            
            // Configuración de servicios
            $table->decimal('preferred_max_cost', 8, 2)->nullable()->comment('Costo máximo preferido para servicios');
            $table->integer('preferred_mechanic_radius')->default(20)->comment('Radio de búsqueda de mecánicos en km');
            $table->json('service_preferences')->nullable()->comment('Preferencias específicas de servicios');
            $table->boolean('auto_accept_quotes')->default(false)->comment('Aceptar cotizaciones automáticamente');
            
            // Información de ubicación laboral/secundaria
            $table->text('work_address')->nullable()->comment('Dirección del trabajo');
            $table->string('work_city', 100)->nullable()->comment('Ciudad del trabajo');
            $table->decimal('work_latitude', 10, 8)->nullable()->comment('Latitud del trabajo');
            $table->decimal('work_longitude', 11, 8)->nullable()->comment('Longitud del trabajo');
            
            // Estadísticas y historial
            $table->integer('total_services_requested')->default(0)->comment('Total de servicios solicitados');
            $table->integer('total_services_completed')->default(0)->comment('Total de servicios completados');
            $table->decimal('total_spent', 10, 2)->default(0.00)->comment('Total gastado en servicios');
            $table->decimal('average_rating_given', 3, 2)->default(0.00)->comment('Promedio de calificaciones dadas');
            $table->integer('total_ratings_given')->default(0)->comment('Total de calificaciones dadas');
            
            // Configuración de la cuenta
            $table->enum('account_type', ['basic', 'premium', 'vip'])->default('basic')->comment('Tipo de cuenta');
            $table->timestamp('premium_expires_at')->nullable()->comment('Fecha de expiración de cuenta premium');
            $table->json('loyalty_points')->nullable()->comment('Sistema de puntos de lealtad');
            
            // Personalización visual
            $table->string('theme_preference', 20)->default('auto')->comment('Preferencia de tema (light/dark/auto)');
            $table->string('language_preference', 10)->default('es')->comment('Idioma preferido');
            $table->json('dashboard_layout')->nullable()->comment('Configuración del layout del dashboard');
            
            // Configuración de privacidad
            $table->boolean('profile_visibility')->default(true)->comment('Perfil visible para mecánicos');
            $table->boolean('show_location')->default(true)->comment('Mostrar ubicación aproximada');
            $table->boolean('allow_mechanic_recommendations')->default(true)->comment('Permitir recomendaciones de mecánicos');
            
            $table->timestamps();
            
            // Índices
            $table->unique('user_id');
            $table->index('account_type');
            $table->index(['preferred_mechanic_radius', 'preferred_max_cost']);
            $table->index(['work_latitude', 'work_longitude'], 'client_work_location_index');
            $table->index('total_services_completed');
            $table->index('premium_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
