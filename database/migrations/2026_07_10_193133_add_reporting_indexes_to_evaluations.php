<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes that speed up the reporting rollups: completion-trend queries
     * filter/sort on completed_at, and manager-activity groups by evaluated_by.
     */
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->index('completed_at');
            $table->index('evaluated_by');
        });
    }

    /**
     * On MySQL the `evaluated_by` index replaced the one InnoDB created for
     * that column's foreign key, so it can't be dropped while the key exists.
     * Drop the key first, then restore it as created — which also recreates
     * the index InnoDB needs.
     */
    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropIndex(['completed_at']);
            $table->dropForeign(['evaluated_by']);
        });

        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropIndex(['evaluated_by']);
            $table->foreign('evaluated_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
