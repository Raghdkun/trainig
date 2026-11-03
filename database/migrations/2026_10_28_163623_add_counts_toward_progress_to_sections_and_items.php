<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the admin mark a station (e.g. Games) or a single item as optional:
     * it can still be worked through, but it is left out of the trainee's
     * completion count, average score and the reports. Defaults to true so all
     * existing content keeps counting exactly as it does today.
     *
     * Each table is checked first, so it can simply be run again if a previous
     * attempt stopped partway (MySQL can't roll schema changes back).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('sections', 'counts_toward_progress')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->boolean('counts_toward_progress')->default(true)->after('status');
            });
        }

        if (! Schema::hasColumn('checklist_items', 'counts_toward_progress')) {
            Schema::table('checklist_items', function (Blueprint $table) {
                $table->boolean('counts_toward_progress')->default(true)->after('requires_rating');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('checklist_items', 'counts_toward_progress')) {
            Schema::table('checklist_items', function (Blueprint $table) {
                $table->dropColumn('counts_toward_progress');
            });
        }

        if (Schema::hasColumn('sections', 'counts_toward_progress')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropColumn('counts_toward_progress');
            });
        }
    }
};
