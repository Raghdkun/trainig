<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each `quizzes` row becomes one immutable version of a station's quiz.
     * Editing a quiz whose link has already been sent creates a new version
     * instead of changing it, so existing links and results stay as they were.
     * Only the latest version is live (`retired_at` null); older ones are kept
     * for their attempts. Existing quizzes simply become version 1.
     *
     * The new (section_id, version) unique is added before the old
     * section_id unique is dropped: on MySQL that old unique is the index
     * backing the section_id foreign key, and InnoDB refuses to drop it until
     * another index leading with that column exists.
     */
    public function up(): void
    {
        // Each step checks first, so it can simply be run again if a previous
        // attempt stopped partway (MySQL can't roll schema changes back).
        if (! Schema::hasColumn('quizzes', 'version')) {
            Schema::table('quizzes', function (Blueprint $table): void {
                $table->unsignedInteger('version')->default(1)->after('section_id');
            });
        }

        if (! Schema::hasColumn('quizzes', 'retired_at')) {
            Schema::table('quizzes', function (Blueprint $table): void {
                $table->timestamp('retired_at')->nullable()->after('version');
            });
        }

        if (! Schema::hasIndex('quizzes', ['section_id', 'version'], 'unique')) {
            Schema::table('quizzes', function (Blueprint $table): void {
                $table->unique(['section_id', 'version']);
            });
        }

        if (Schema::hasIndex('quizzes', ['section_id'], 'unique')) {
            Schema::table('quizzes', function (Blueprint $table): void {
                $table->dropUnique(['section_id']);
            });
        }
    }

    /**
     * Only safe while each section still has a single version — otherwise
     * restoring the one-quiz-per-section unique would fail.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->unique(['section_id']);
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropUnique(['section_id', 'version']);
            $table->dropColumn(['version', 'retired_at']);
        });
    }
};
