<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One answer per assessment question per evaluation, stored raw: 0/1 for
     * yes/no, 1–5 for a level, 0–100 for a percentage.
     */
    public function up(): void
    {
        Schema::create('development_evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_question_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('value');
            $table->timestamps();

            $table->unique(['development_evaluation_id', 'assessment_question_id'], 'dev_eval_answer_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_evaluation_answers');
    }
};
