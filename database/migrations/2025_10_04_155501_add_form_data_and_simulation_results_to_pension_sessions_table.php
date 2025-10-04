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
        Schema::table('pension_sessions', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('calculation_data');
            $table->json('simulation_results')->nullable()->after('form_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pension_sessions', function (Blueprint $table) {
            $table->dropColumn(['form_data', 'simulation_results']);
        });
    }
};
