<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('population_data_archive', function (Blueprint $table) {
            // 'auto'   = routine archive created on every household sync
            // 'annual' = deliberate year-end snapshot (manual button or cron)
            $table->string('snapshot_type', 20)->default('auto')->after('change_type');
        });
    }

    public function down(): void
    {
        Schema::table('population_data_archive', function (Blueprint $table) {
            $table->dropColumn('snapshot_type');
        });
    }
};
