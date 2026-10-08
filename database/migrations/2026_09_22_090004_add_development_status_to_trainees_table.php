<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each step checks first, so the migration can simply be run again if a
     * previous attempt stopped partway (MySQL can't roll schema changes back).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('trainees', 'development_status')) {
            Schema::table('trainees', function (Blueprint $table) {
                $table->string('development_status')->nullable()->after('needs_development');
            });
        }

        if (! Schema::hasIndex('trainees', ['development_status'])) {
            Schema::table('trainees', function (Blueprint $table) {
                $table->index('development_status');
            });
        }

        // Already converted and dropped by an earlier run.
        if (! Schema::hasColumn('trainees', 'needs_development')) {
            return;
        }

        DB::table('trainees')
            ->where('needs_development', true)
            ->whereIn('id', function ($query) {
                $query->select('trainee_id')->from('development_plan_items');
            })
            ->update(['development_status' => 'active']);

        DB::table('trainees')
            ->where('needs_development', true)
            ->whereNotIn('id', function ($query) {
                $query->select('trainee_id')->from('development_plan_items');
            })
            ->update(['development_status' => 'pending']);

        Schema::table('trainees', function (Blueprint $table) {
            $table->dropColumn('needs_development');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            $table->boolean('needs_development')->default(false)->after('archived_by');
        });

        DB::table('trainees')
            ->whereNotNull('development_status')
            ->update(['needs_development' => true]);

        // SQLite refuses to drop a column that still has an index on it.
        Schema::table('trainees', function (Blueprint $table) {
            $table->dropIndex(['development_status']);
            $table->dropColumn('development_status');
        });
    }
};
