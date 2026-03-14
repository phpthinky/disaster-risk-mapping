<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_boundary_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->enum('action', ['created', 'updated']);
            $table->foreignId('drawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('drawn_at')->useCurrent();
        });

        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('hazard_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('incident_date');
            $table->enum('status', ['ongoing', 'resolved', 'monitoring'])->default('ongoing');
            $table->longText('affected_polygon')->nullable()->comment('GeoJSON polygon of the affected area');
            $table->text('description')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('affected_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incident_reports')->cascadeOnDelete();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->integer('affected_households')->default(0);
            $table->integer('affected_population')->default(0);
            $table->integer('affected_pwd')->default(0);
            $table->integer('affected_seniors')->default(0);
            $table->integer('affected_infants')->default(0);
            $table->integer('affected_minors')->default(0);
            $table->integer('affected_pregnant')->default(0);
            $table->integer('ip_count')->default(0);
            $table->timestamp('computed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affected_areas');
        Schema::dropIfExists('incident_reports');
        Schema::dropIfExists('barangay_boundary_logs');
    }
};
