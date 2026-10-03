<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Intentionally empty. The quiz answers table used to be created under
     * this name, which ran before `quiz_attempts` existed and failed on MySQL.
     * It now lives in `2026_09_17_141031_create_quiz_answers_table`.
     *
     * This file stays so that uploading the app over an older copy replaces
     * the old version of it (which would otherwise run again and fail),
     * instead of leaving it behind.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
