<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A reassessment is an evaluation run later by the training team to
     * measure improvement — it doesn't restart the Development Zone workflow.
     */
    public function up(): void
    {
        Schema::table('development_evaluations', function (Blueprint $table) {
            $table->boolean('is_reassessment')->default(false)->after('evaluated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_evaluations', function (Blueprint $table) {
            $table->dropColumn('is_reassessment');
        });
    }
};
