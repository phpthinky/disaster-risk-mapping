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
        Schema::table('hazard_zones', function (Blueprint $table) {
            // Increase precision from decimal(10,2) to decimal(10,4) so that
            // the 4-decimal area values auto-calculated by the Leaflet map
            // can be stored without truncation or browser validation rejection.
            $table->decimal('area_km2', 10, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hazard_zones', function (Blueprint $table) {
            $table->decimal('area_km2', 10, 2)->nullable()->change();
        });
    }
};
