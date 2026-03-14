<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('household_head');
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('zone', 50)->nullable();
            $table->enum('sex', ['Male', 'Female']);
            $table->integer('age');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->string('house_type', 100)->nullable();
            // Computed counts (set by SyncService)
            $table->integer('family_members')->default(1);
            $table->integer('pwd_count')->default(0);
            $table->integer('pregnant_count')->default(0);
            $table->integer('senior_count')->default(0);
            $table->integer('infant_count')->default(0);
            $table->integer('minor_count')->default(0);
            $table->integer('child_count')->default(0);
            $table->integer('adolescent_count')->default(0);
            $table->integer('young_adult_count')->default(0);
            $table->integer('adult_count')->default(0);
            $table->integer('middle_aged_count')->default(0);
            // GPS (required for new entries)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('sitio_purok_zone')->nullable();
            $table->enum('ip_non_ip', ['IP', 'Non-IP'])->nullable();
            $table->string('hh_id', 100)->nullable();
            $table->enum('preparedness_kit', ['Yes', 'No'])->nullable();
            $table->string('educational_attainment')->nullable();
            $table->timestamps();
        });

        Schema::create('household_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->integer('age');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->string('relationship', 100);
            $table->boolean('is_pwd')->default(false);
            $table->boolean('is_pregnant')->default(false);
            $table->boolean('is_senior')->default(false);
            $table->boolean('is_infant')->default(false);
            $table->boolean('is_minor')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');
    }
};
