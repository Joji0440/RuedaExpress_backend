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
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('mechanic_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('set null');
            $table->foreignId('preferred_mechanic_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Información básica
            $table->string('title');
            $table->text('description');
            $table->string('service_type');
            $table->enum('urgency_level', ['baja', 'media', 'alta', 'critica'])->default('media');
            $table->decimal('estimated_duration_hours', 4, 2);
            $table->decimal('budget_max', 10, 2);
            $table->boolean('is_emergency')->default(false);
            
            // Programación
            $table->date('preferred_date')->nullable();
            
            // Ubicación
            $table->text('location_address')->nullable();
            $table->text('location_notes')->nullable();
            
            // Estado y seguimiento
            $table->enum('status', ['pending', 'accepted', 'in_progress', 'completed', 'cancelled', 'rejected'])->default('pending');
            $table->decimal('final_cost', 10, 2)->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['client_id', 'status']);
            $table->index(['mechanic_id', 'status']);
            $table->index('status');
            $table->index('urgency_level');
            $table->index('is_emergency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
