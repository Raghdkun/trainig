<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Development Zone assessment questions, authored per station (section).
     * Each one measures the employee's knowledge or ability at that station
     * with a simple answer: yes/no, a 1–5 level, or a percentage.
     */
    public function up(): void
    {
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('prompt', 500);
            $table->string('answer_type');
            $table->unsignedInteger('order')->default(0);
            // Questions already answered in an assessment are retired instead
            // of deleted, so past answers keep pointing at them.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['section_id', 'is_active', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_questions');
    }
};
