<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('age');
        });

        Schema::table('household_members', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('age');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });

        Schema::table('household_members', function (Blueprint $table) {
            $table->dropColumn('birthday');
        });
    }
};
