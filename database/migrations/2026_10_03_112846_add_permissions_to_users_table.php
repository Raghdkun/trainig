<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra abilities a super admin has explicitly granted to a manager (see
     * App\Enums\Permission). Super admins hold every permission implicitly,
     * so this stays empty for them.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'permissions')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
