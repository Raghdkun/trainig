<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The importance taxonomy changed: `not_necessary` → `optional` and
     * `needs_review` → `moderately_important` (`highly_important` is unchanged).
     * Rewrite any existing rows so they keep matching the enum. Data-only and
     * fully reversible — the column stays a plain nullable string.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'not_necessary' => 'optional',
        'needs_review' => 'moderately_important',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('checklist_items')
                ->where('importance', $old)
                ->update(['importance' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('checklist_items')
                ->where('importance', $new)
                ->update(['importance' => $old]);
        }
    }
};
