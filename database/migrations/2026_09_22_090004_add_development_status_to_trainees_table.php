<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            $table->string('development_status')->nullable()->after('needs_development')->index();
        });

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

        Schema::table('trainees', function (Blueprint $table) {
            $table->dropColumn('development_status');
        });
    }
};
