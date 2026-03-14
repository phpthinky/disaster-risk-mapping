<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('population_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('total_population')->nullable();
            $table->integer('households')->nullable();
            $table->integer('elderly_count')->nullable();
            $table->integer('children_count')->nullable();
            $table->integer('pwd_count')->nullable();
            $table->integer('ips_count')->default(0);
            $table->integer('solo_parent_count')->default(0);
            $table->integer('widow_count')->default(0);
            $table->date('data_date')->nullable();
            $table->string('entered_by', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('population_data_archive', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id');
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->integer('total_population');
            $table->integer('households');
            $table->integer('elderly_count');
            $table->integer('children_count');
            $table->integer('pwd_count');
            $table->integer('ips_count')->default(0);
            $table->integer('solo_parent_count')->default(0);
            $table->integer('widow_count')->default(0);
            $table->date('data_date');
            $table->timestamp('archived_at')->useCurrent();
            $table->string('archived_by', 50)->nullable();
            $table->string('change_type', 20)->nullable()->comment('UPDATE or DELETE');

            $table->index('barangay_id', 'idx_archive_barangay');
            $table->index('archived_at', 'idx_archive_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('population_data_archive');
        Schema::dropIfExists('population_data');
    }
};
