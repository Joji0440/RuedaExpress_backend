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
        Schema::table('users', function (Blueprint $table) {
            // Información de contacto y perfil
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('profile_photo_path')->nullable()->after('phone');
            
            // Información de ubicación
            $table->text('address')->nullable()->after('profile_photo_path');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->decimal('latitude', 10, 8)->nullable()->after('postal_code');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->timestamp('last_location_update')->nullable()->after('longitude');
            
            // Estado del usuario
            $table->boolean('is_active')->default(true)->after('last_location_update');
            
            // Índices para búsquedas geográficas
            $table->index(['latitude', 'longitude'], 'users_location_index');
            $table->index('city');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_location_index');
            $table->dropIndex(['city']);
            $table->dropIndex(['is_active']);
            
            $table->dropColumn([
                'phone',
                'profile_photo_path',
                'address', 
                'city',
                'state',
                'postal_code',
                'latitude',
                'longitude',
                'last_location_update',
                'is_active'
            ]);
        });
    }
};
