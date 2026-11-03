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
        Schema::create('development_evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'order']);
        });

        $now = now();

        DB::table('development_evaluation_criteria')->insert([
            ['label' => 'Overall Performance', 'description' => null, 'order' => 0, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Speed', 'description' => null, 'order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Standards / Quality', 'description' => null, 'order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Work Execution', 'description' => null, 'order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Attitude / Teamwork', 'description' => null, 'order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_evaluation_criteria');
    }
};
