<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Free-text positions that clearly mean one of the new fixed positions,
     * matched case-insensitively after trimming. Values are hard-coded (not
     * read from the Position enum) so this migration never changes meaning.
     *
     * @var array<string, list<string>>
     */
    private const MAPPING = [
        'Crew Member' => ['crew member', 'cm'],
        'Crew Leader' => ['crew leader', 'shift lead', 'shift leader', 'cl'],
        'Assistant Manager' => ['assistant manager', 'am'],
    ];

    /**
     * Map known legacy positions onto the new fixed values. Anything else is
     * left exactly as it is — it keeps displaying, and gets replaced when
     * someone picks a position for that trainee.
     */
    public function up(): void
    {
        foreach (self::MAPPING as $position => $aliases) {
            $placeholders = implode(', ', array_fill(0, count($aliases), '?'));

            DB::table('trainees')
                ->whereNotNull('position')
                ->whereRaw("LOWER(TRIM(position)) IN ({$placeholders})", $aliases)
                ->update(['position' => $position]);
        }
    }

    /**
     * Not reversible — the original spelling of each value isn't kept, and
     * the mapped values are still valid positions, so nothing breaks.
     */
    public function down(): void
    {
        //
    }
};
