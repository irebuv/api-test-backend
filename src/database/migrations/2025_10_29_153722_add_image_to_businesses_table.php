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
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('image_original')->nullable()->after('image');
            $table->string('image_thumb_webp')->nullable()->after('image_original');
            $table->string('image_card_webp')->nullable()->after('image_thumb_webp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['image_original', 'image_thumb_webp', 'image_card_webp']);
        });
    }
};
