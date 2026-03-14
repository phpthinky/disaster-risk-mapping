<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('population')->default(0);
            $table->decimal('area_km2', 10, 2)->nullable();
            $table->decimal('calculated_area_km2', 10, 4)->nullable()->comment('Area auto-calculated from boundary polygon (km²)');
            $table->string('coordinates', 255)->nullable();
            $table->longText('boundary_geojson')->nullable()->comment('GeoJSON polygon drawn by admin for barangay boundary');
            // Computed population columns (filled by SyncService)
            $table->integer('household_count')->default(0);
            $table->integer('pwd_count')->default(0);
            $table->integer('senior_count')->default(0);
            $table->integer('children_count')->default(0);
            $table->integer('infant_count')->default(0);
            $table->integer('pregnant_count')->default(0);
            $table->integer('ip_count')->default(0);
            $table->timestamps();
        });

        // Add FK for users.barangay_id now that barangays exists
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('barangay_id')->references('id')->on('barangays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['barangay_id']);
        });
        Schema::dropIfExists('barangays');
    }
};
