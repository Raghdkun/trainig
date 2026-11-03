<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
     *
     * Each step checks first, because a server whose `quiz_answers` table
     * was repaired (see `create_quiz_answers_table`) may not have the old
     * unique, and may hold the same selection twice from before any unique
     * existed.
     */
    public function up(): void
    {
        $columns = ['quiz_attempt_id', 'quiz_question_id', 'quiz_question_option_id'];

        if (! Schema::hasIndex('quiz_answers', $columns, 'unique')) {
            $this->removeDuplicateSelections($columns);

            Schema::table('quiz_answers', function (Blueprint $table) use ($columns): void {
                $table->unique($columns, 'quiz_answers_attempt_question_option_unique');
            });
        }

        if (Schema::hasIndex('quiz_answers', ['quiz_attempt_id', 'quiz_question_id'], 'unique')) {
            Schema::table('quiz_answers', function (Blueprint $table): void {
                $table->dropUnique(['quiz_attempt_id', 'quiz_question_id']);
            });
        }
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

    /**
     * Keep the first row of each identical selection and delete the copies.
     *
     * @param  list<string>  $columns
     */
    private function removeDuplicateSelections(array $columns): void
    {
        DB::table('quiz_answers')
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(function (object $duplicate) use ($columns): void {
                $copies = DB::table('quiz_answers');

                foreach ($columns as $column) {
                    $copies->where($column, $duplicate->{$column});
                }

                $copies->where('id', '>', (clone $copies)->min('id'))->delete();
            });
    }
};
