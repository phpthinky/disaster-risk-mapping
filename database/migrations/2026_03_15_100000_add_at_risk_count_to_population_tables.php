<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // barangays — live computed column
        Schema::table('barangays', function (Blueprint $table) {
            $table->integer('at_risk_count')->default(0)
                ->after('ip_count')
                ->comment('Households (by population) whose GPS falls inside any susceptible hazard zone');
        });

        // population_data — current snapshot mirror
        Schema::table('population_data', function (Blueprint $table) {
            $table->integer('at_risk_count')->default(0)->after('ips_count');
        });

        // population_data_archive — historical snapshots
        Schema::table('population_data_archive', function (Blueprint $table) {
            $table->integer('at_risk_count')->default(0)->after('ips_count');
        });
    }

    public function down(): void
    {
        Schema::table('population_data_archive', function (Blueprint $table) {
            $table->dropColumn('at_risk_count');
        });
        Schema::table('population_data', function (Blueprint $table) {
            $table->dropColumn('at_risk_count');
        });
        Schema::table('barangays', function (Blueprint $table) {
            $table->dropColumn('at_risk_count');
        });
    }
};
