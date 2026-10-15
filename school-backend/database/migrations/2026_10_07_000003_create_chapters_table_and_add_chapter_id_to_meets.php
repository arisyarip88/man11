<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order')->default(1);
            $table->timestamps();
        });

        Schema::table('meets', function (Blueprint $table) {
            $table->foreignId('chapter_id')->nullable()->after('course_id')->constrained('chapters')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('meets', function (Blueprint $table) {
            $table->dropForeign(['chapter_id']);
            $table->dropColumn('chapter_id');
        });

        Schema::dropIfExists('chapters');
    }
};
