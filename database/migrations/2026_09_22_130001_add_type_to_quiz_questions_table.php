<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safe to run again if an earlier attempt stopped after adding it.
        if (Schema::hasColumn('quiz_questions', 'type')) {
            return;
        }

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->string('type')->default('single')->after('prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
