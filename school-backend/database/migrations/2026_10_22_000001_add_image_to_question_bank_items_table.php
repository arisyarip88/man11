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
        Schema::table('question_bank_items', function (Blueprint $table) {
            if (!Schema::hasColumn('question_bank_items', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('question');
            }
            if (!Schema::hasColumn('question_bank_items', 'image_meta')) {
                $table->json('image_meta')->nullable()->after('image_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_bank_items', function (Blueprint $table) {
            if (Schema::hasColumn('question_bank_items', 'image_meta')) {
                $table->dropColumn('image_meta');
            }
            if (Schema::hasColumn('question_bank_items', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }
};
