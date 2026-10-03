<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each foreign key column and the table it points at.
     *
     * @var array<string, string>
     */
    private const FOREIGN_KEYS = [
        'quiz_attempt_id' => 'quiz_attempts',
        'quiz_question_id' => 'quiz_questions',
        'quiz_question_option_id' => 'quiz_question_options',
    ];

    /**
     * This migration was first released as `2026_09_17_141030_create_quiz_answers_table`,
     * which ran before `quiz_attempts` existed. On MySQL that left a
     * `quiz_answers` table behind with no foreign keys (MySQL can't roll back
     * a CREATE TABLE), so a server that hit that error already has the table.
     * In that case it's repaired in place instead of created again.
     */
    public function up(): void
    {
        if (Schema::hasTable('quiz_answers')) {
            $this->repairLeftoverTable();

            return;
        }

        Schema::create('quiz_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_question_option_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'quiz_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }

    /**
     * Add whatever the failed run didn't get to, keeping any rows that are
     * already there. Rows pointing at something that no longer exists are
     * removed first: they could never be shown, and the foreign keys would
     * reject them (and cascade-delete them anyway).
     */
    private function repairLeftoverTable(): void
    {
        foreach (self::FOREIGN_KEYS as $column => $references) {
            DB::table('quiz_answers')
                ->whereNotIn($column, fn ($query) => $query->select('id')->from($references))
                ->delete();
        }

        $existingForeignKeyColumns = collect(Schema::getForeignKeys('quiz_answers'))
            ->flatMap(fn (array $foreignKey): array => $foreignKey['columns'])
            ->all();

        foreach (self::FOREIGN_KEYS as $column => $references) {
            if (in_array($column, $existingForeignKeyColumns, true)) {
                continue;
            }

            Schema::table('quiz_answers', function (Blueprint $table) use ($column, $references): void {
                $table->foreign($column)->references('id')->on($references)->cascadeOnDelete();
            });
        }

        $this->addOriginalUniqueIfPossible();
    }

    /**
     * The original one-answer-per-question unique. Skipped when the table
     * already has the wider unique, or holds several answers to one question
     * (multi-answer questions): the later
     * `widen_quiz_answers_unique_constraint` migration adds the wider unique
     * in that case.
     */
    private function addOriginalUniqueIfPossible(): void
    {
        $columns = ['quiz_attempt_id', 'quiz_question_id'];

        if (Schema::hasIndex('quiz_answers', $columns, 'unique')
            || Schema::hasIndex('quiz_answers', [...$columns, 'quiz_question_option_id'], 'unique')) {
            return;
        }

        $hasDuplicates = DB::table('quiz_answers')
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('count(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            return;
        }

        Schema::table('quiz_answers', function (Blueprint $table) use ($columns): void {
            $table->unique($columns);
        });
    }
};
