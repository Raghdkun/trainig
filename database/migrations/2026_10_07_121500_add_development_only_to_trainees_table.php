<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employees a manager adds straight into the Development Zone (rather
     * than picking an existing trainee) are flagged so they stay out of the
     * Trainees roster, dashboard counts and roster reports.
     */
    public function up(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            $table->boolean('development_only')->default(false)->after('development_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            $table->dropColumn('development_only');
        });
    }
};
