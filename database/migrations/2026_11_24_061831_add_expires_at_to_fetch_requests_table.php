<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fetch_requests', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fetch_requests', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
