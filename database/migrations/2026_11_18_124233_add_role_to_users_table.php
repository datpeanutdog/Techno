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
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role')->default('user')->index();
            });
        }

        $adminEmails = config('app.admin_emails', []);

        if ($adminEmails !== []) {
            DB::table('users')
                ->whereIn(DB::raw('LOWER(email)'), $adminEmails)
                ->update(['role' => 'admin']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            });
        }
    }
};
