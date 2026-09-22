<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A multi-choice question now stores one answer row per selected option,
     * so (attempt, question) alone is no longer unique — widen it to include
     * the option so each selection gets its own row.
     *
     * The new index is added before the old one is dropped: on MySQL the old
     * composite unique is what satisfies the `quiz_attempt_id` foreign key's
     * index requirement, and InnoDB refuses to drop it until another index
     * leading with that column exists.
     */
    public function up(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table): void {
            $table->unique(
                ['quiz_attempt_id', 'quiz_question_id', 'quiz_question_option_id'],
                'quiz_answers_attempt_question_option_unique',
            );
        });

        Schema::table('quiz_answers', function (Blueprint $table): void {
            $table->dropUnique(['quiz_attempt_id', 'quiz_question_id']);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table): void {
            $table->unique(['quiz_attempt_id', 'quiz_question_id']);
        });

        Schema::table('quiz_answers', function (Blueprint $table): void {
            $table->dropUnique('quiz_answers_attempt_question_option_unique');
        });
    }
};
