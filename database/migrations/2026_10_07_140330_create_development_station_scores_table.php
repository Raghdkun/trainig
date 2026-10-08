<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each station's star rating (0–5 in ¼-star steps) as calculated when the
     * evaluation was submitted. Stored rather than recomputed so an
     * employee's history never shifts when questions are edited later.
     */
    public function up(): void
    {
        Schema::create('development_station_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('section_title');
            $table->decimal('stars', 3, 2);
            $table->timestamps();

            $table->unique(['development_evaluation_id', 'section_id'], 'dev_station_score_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_station_scores');
    }
};
