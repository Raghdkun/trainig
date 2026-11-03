<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the trainee opened the link and confirmed it's meant for them —
     * lets the training team tell "link created" apart from "in progress".
     */
    public function up(): void
    {
        // Safe to run again if an earlier attempt stopped after adding it
        // (the backfill below only fills blanks, so repeating it is harmless).
        if (! Schema::hasColumn('quiz_attempts', 'started_at')) {
            Schema::table('quiz_attempts', function (Blueprint $table): void {
                $table->timestamp('started_at')->nullable()->after('sent_at');
            });
        }

        // Quizzes finished before this was tracked were obviously started —
        // use the submit time so their timeline isn't left with a gap.
        // `sent_at` is re-set to itself so a MySQL server running with
        // explicit_defaults_for_timestamp=OFF (where the first TIMESTAMP
        // column auto-updates on every write) can't overwrite send dates.
        DB::table('quiz_attempts')
            ->whereNotNull('completed_at')
            ->update([
                'started_at' => DB::raw('completed_at'),
                'sent_at' => DB::raw('sent_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->dropColumn('started_at');
        });
    }
};
