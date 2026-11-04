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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 90);
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('price', 12, 2);
            $table->string('type', 10)->index();
            $table->string('rent_period', 10)->nullable();
            $table->string('category', 40)->index();
            $table->string('location', 100)->index();
            $table->string('image_url', 2048)->nullable();
            $table->string('seller_name', 60);
            $table->string('seller_initials', 4);
            $table->decimal('seller_rating', 2, 1)->default(5.0);
            $table->string('condition', 20)->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('is_available')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
