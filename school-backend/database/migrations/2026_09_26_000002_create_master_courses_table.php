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
        // 1. Master Courses (Template Kurikulum & Pertemuan Induk Guru)
        Schema::create('master_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->string('code', 50)->unique(); // Contoh: 'KM-2025-001'
            $table->string('name');
            $table->string('grade_level', 10); // '10', '11', '12'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Pivot Master Course ke Rombel (classes)
        Schema::create('master_course_rombels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_course_id')->constrained('master_courses')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['master_course_id', 'rombel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_course_rombels');
        Schema::dropIfExists('master_courses');
    }
};
