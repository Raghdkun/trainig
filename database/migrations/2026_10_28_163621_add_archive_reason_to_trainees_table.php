<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the reason a trainee was archived on the trainee itself, so an
     * admin can still read it after the manager's request has been approved.
     *
     * Each step checks first, so it can simply be run again if a previous
     * attempt stopped partway (MySQL can't roll schema changes back).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('trainees', 'archive_reason')) {
            Schema::table('trainees', function (Blueprint $table) {
                $table->text('archive_reason')->nullable()->after('archived_by');
            });
        }

        // Trainees archived before this column existed: carry over the reason
        // from their approved request (the latest id wins if there are several).
        // Only fills empty values, so a re-run never overwrites anything.
        $reasons = DB::table('trainee_archive_requests')
            ->where('status', 'approved')
            ->orderBy('id')
            ->pluck('reason', 'trainee_id');

        foreach ($reasons as $traineeId => $reason) {
            DB::table('trainees')
                ->where('id', $traineeId)
                ->whereNotNull('archived_at')
                ->whereNull('archive_reason')
                ->update(['archive_reason' => $reason]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('trainees', 'archive_reason')) {
            Schema::table('trainees', function (Blueprint $table) {
                $table->dropColumn('archive_reason');
            });
        }
    }
};
