<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hazard_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('color', 7)->nullable()->comment('Hex color code');
            $table->string('icon', 50)->nullable()->comment('Font Awesome icon class');
        });

        Schema::create('hazard_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hazard_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('risk_level', [
                'High Susceptible',
                'Moderate Susceptible',
                'Low Susceptible',
                'Not Susceptible',
                'Prone',
                'Generally Susceptible',
                'PEIS VIII - Very destructive to devastating ground shaking',
                'PEIS VII - Destructive ground shaking',
                'General Inundation',
            ])->nullable();
            $table->decimal('area_km2', 10, 2)->nullable();
            $table->integer('affected_population')->nullable();
            $table->text('coordinates')->nullable()->comment('GeoJSON coordinates');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hazard_zones');
        Schema::dropIfExists('hazard_types');
    }
};
