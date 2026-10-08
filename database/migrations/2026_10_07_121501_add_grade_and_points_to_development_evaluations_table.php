<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The manager's overall A–D grade and 0–100 points for the employee.
     * Nullable so evaluations submitted before these existed stay valid.
     */
    public function up(): void
    {
        Schema::table('development_evaluations', function (Blueprint $table) {
            $table->string('grade', 1)->nullable()->after('evaluated_by');
            $table->unsignedTinyInteger('points')->nullable()->after('grade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_evaluations', function (Blueprint $table) {
            $table->dropColumn(['grade', 'points']);
        });
    }
};
