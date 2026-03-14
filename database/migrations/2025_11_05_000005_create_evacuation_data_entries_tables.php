<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evacuation_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->integer('capacity');
            $table->integer('current_occupancy')->default(0);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('facilities')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->enum('status', ['operational', 'maintenance', 'closed'])->default('operational');
            $table->timestamps();
        });

        Schema::create('data_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('data_type', ['population', 'hazard', 'general'])->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_entries');
        Schema::dropIfExists('evacuation_centers');
    }
};
