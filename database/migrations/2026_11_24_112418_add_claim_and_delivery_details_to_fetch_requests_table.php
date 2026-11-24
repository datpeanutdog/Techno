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
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivery_deadline')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fetch_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accepted_by_id');
            $table->dropIndex(['status']);
            $table->dropIndex(['delivery_deadline']);
            $table->dropColumn([
                'status',
                'accepted_at',
                'delivery_deadline',
                'delivered_at',
                'confirmed_at',
            ]);
        });
    }
};
