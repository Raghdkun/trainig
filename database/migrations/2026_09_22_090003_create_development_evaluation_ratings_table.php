<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The foreign keys carry explicit short names: Laravel's generated names
     * (table + column + `_foreign`) run to 74 characters here, past MySQL's
     * 64-character identifier limit.
     */
    public function up(): void
    {
        Schema::create('development_evaluation_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_evaluation_id')
                ->constrained(indexName: 'dev_eval_ratings_evaluation_foreign')
                ->cascadeOnDelete();
            $table->foreignId('development_evaluation_criterion_id')
                ->constrained(indexName: 'dev_eval_ratings_criterion_foreign')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();

            $table->unique(['development_evaluation_id', 'development_evaluation_criterion_id'], 'dev_eval_rating_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_evaluation_ratings');
    }
};
