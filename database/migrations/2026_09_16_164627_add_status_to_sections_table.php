<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Defaults to "published" so every existing station (and any row
     * inserted outside the builder) stays visible — only stations created
     * through the builder from here on start as "draft" (set explicitly by
     * SectionController::store()).
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('status')->default('published')->after('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
