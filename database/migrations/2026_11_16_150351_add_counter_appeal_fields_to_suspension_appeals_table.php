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
        Schema::table('suspension_appeals', function (Blueprint $table): void {
            $table->foreignId('previous_appeal_id')
                ->nullable()
                ->constrained('suspension_appeals')
                ->nullOnDelete();
            $table->string('proof_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suspension_appeals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('previous_appeal_id');
            $table->dropColumn('proof_path');
        });
    }
};
