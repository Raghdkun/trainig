<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed at 4 choices per question (A–D), authored together with the
     * question — one flagged correct. Keeps the authoring form simple
     * (no dynamic add/remove rows) for what is meant to be a quick,
     * lightweight comprehension check.
     */
    public function up(): void
    {
        Schema::create('quiz_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->string('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_question_options');
    }
};
