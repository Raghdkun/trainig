<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assessment questions move off Content Builder stations onto their own
     * "stations & skills" list (Making, Cleaning, Communication, …), which can
     * optionally link to a content station for plan suggestions.
     *
     * Existing questions keep working: each content station that had
     * questions becomes a skill of the same name, linked to that station.
     * Saved ratings move to per-skill scores the same way.
     */
    public function up(): void
    {
        Schema::create('assessment_skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            // Optional link to a content station, whose training content is
            // suggested when this skill is a development need.
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $skillForSection = $this->createSkillsForStationsWithQuestions();

        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->foreignId('assessment_skill_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        foreach ($skillForSection as $sectionId => $skillId) {
            DB::table('assessment_questions')->where('section_id', $sectionId)->update(['assessment_skill_id' => $skillId]);
        }

        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropIndex(['section_id', 'is_active', 'order']);
            $table->dropColumn('section_id');
            $table->index(['assessment_skill_id', 'is_active', 'order']);
        });

        Schema::create('development_skill_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_skill_id')->nullable()->constrained()->nullOnDelete();
            // Frozen at submission so history reads the same if the skill is
            // renamed, relinked or removed later.
            $table->string('skill_name');
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('stars', 3, 2);
            $table->timestamps();

            $table->unique(['development_evaluation_id', 'assessment_skill_id'], 'dev_skill_score_unique');
        });

        foreach (DB::table('development_station_scores')->orderBy('id')->get() as $score) {
            DB::table('development_skill_scores')->insert([
                'development_evaluation_id' => $score->development_evaluation_id,
                'assessment_skill_id' => $score->section_id !== null ? ($skillForSection[$score->section_id] ?? null) : null,
                'skill_name' => $score->section_title,
                'section_id' => $score->section_id,
                'stars' => $score->stars,
                'created_at' => $score->created_at,
                'updated_at' => $score->updated_at,
            ]);
        }

        Schema::drop('development_station_scores');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('development_station_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('section_title');
            $table->decimal('stars', 3, 2);
            $table->timestamps();

            $table->unique(['development_evaluation_id', 'section_id'], 'dev_station_score_unique');
        });

        Schema::drop('development_skill_scores');

        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        foreach (DB::table('assessment_skills')->whereNotNull('section_id')->get() as $skill) {
            DB::table('assessment_questions')->where('assessment_skill_id', $skill->id)->update(['section_id' => $skill->section_id]);
        }

        DB::table('assessment_questions')->whereNull('section_id')->delete();

        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropForeign(['assessment_skill_id']);
            $table->dropIndex(['assessment_skill_id', 'is_active', 'order']);
            $table->dropColumn('assessment_skill_id');
            $table->index(['section_id', 'is_active', 'order']);
        });

        Schema::dropIfExists('assessment_skills');
    }

    /**
     * @return array<int, int> content station id => new skill id
     */
    private function createSkillsForStationsWithQuestions(): array
    {
        $map = [];

        $sectionIds = DB::table('assessment_questions')->distinct()->pluck('section_id');
        $sections = DB::table('sections')->whereIn('id', $sectionIds)->orderBy('order')->get(['id', 'title', 'order']);

        foreach ($sections as $section) {
            $map[$section->id] = DB::table('assessment_skills')->insertGetId([
                'name' => $section->title,
                'section_id' => $section->id,
                'order' => $section->order,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $map;
    }
};
