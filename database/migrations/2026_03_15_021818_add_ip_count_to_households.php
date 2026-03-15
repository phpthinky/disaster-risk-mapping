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
        Schema::table('households', function (Blueprint $table) {
            // Track IP people count per household (head + IP members).
            // Computed by recomputeHousehold(); aggregated by syncBarangay().
            $table->unsignedInteger('ip_count')->default(0)->after('ip_non_ip');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('ip_count');
        });
    }
};
