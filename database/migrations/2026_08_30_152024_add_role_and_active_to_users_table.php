<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a lightweight role + active flag to users. New accounts default to the
     * least-privileged "staff" role so nobody is accidentally over-granted.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        // Safe promotion of the primary account so the app is never left without a
        // Super Admin. We never hard-code personal credentials: an explicit
        // SUPER_ADMIN_EMAIL wins; otherwise the earliest-created account (the one
        // the workspace was set up with) is promoted. On a fresh/empty database
        // this simply affects nothing.
        $primaryEmail = env('SUPER_ADMIN_EMAIL');

        if ($primaryEmail) {
            DB::table('users')->where('email', $primaryEmail)
                ->update(['role' => 'super_admin', 'is_active' => true]);
        }

        $hasSuperAdmin = DB::table('users')->where('role', 'super_admin')->exists();

        if (! $hasSuperAdmin) {
            $first = DB::table('users')->orderBy('id')->first();
            if ($first) {
                DB::table('users')->where('id', $first->id)
                    ->update(['role' => 'super_admin', 'is_active' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
