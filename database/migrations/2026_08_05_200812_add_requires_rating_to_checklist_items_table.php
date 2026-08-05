<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Not every item is scored — some are just "done / not done". This flag
     * lets the admin decide per item. Defaults to true so existing content
     * keeps its current "must be rated to complete" behaviour.
     */
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->boolean('requires_rating')->default(true)->after('importance');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->dropColumn('requires_rating');
        });
    }
};
