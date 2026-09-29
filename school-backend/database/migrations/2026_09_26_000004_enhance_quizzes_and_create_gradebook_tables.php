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
        // 1. Tambah fitur CBT & Bank Soal pada tabel quizzes
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dateTime('end_time')->nullable()->after('start_time');
            $table->boolean('is_random_question')->default(false)->after('duration_minutes');
            $table->boolean('is_random_option')->default(false)->after('is_random_question');
            $table->foreignId('question_bank_id')->nullable()->after('is_random_option')->constrained('question_banks')->nullOnDelete();
            $table->json('questions_data')->nullable()->after('question_bank_id'); // Snapshot butir soal kuis
        });

        // 2. Tambah fitur autosave & grading breakdown pada quiz_submissions
        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->after('student_id');
            $table->json('answers')->nullable()->after('submission_text'); // Format: { "1": { "answer": "A", "is_correct": true } }
            $table->decimal('score_pg', 5, 2)->nullable()->after('score');
            $table->decimal('score_essay', 5, 2)->nullable()->after('score_pg');
            $table->boolean('is_graded')->default(false)->after('score_essay');
        });

        // 3. Komponen Nilai Kurikulum Merdeka (Formatif, STS, SAS)
        Schema::create('score_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('type', ['formatif', 'sumatif_materi', 'sumatif_akhir'])->default('formatif');
            $table->string('code', 50); // Contoh: 'TP-1', 'TP-2', 'STS', 'SAS'
            $table->string('title'); // Contoh: 'Tugas Fungsi Aljabar', 'Ulangan Tengah Semester'
            $table->decimal('weight', 5, 2)->default(1.00); // Bobot dalam rapor
            $table->timestamps();
        });

        // 4. Rekap Nilai Siswa per Komponen
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_component_id')->constrained('score_components')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0.00);
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['score_component_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
        Schema::dropIfExists('score_components');

        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'answers', 'score_pg', 'score_essay', 'is_graded']);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropForeign(['question_bank_id']);
            $table->dropColumn(['end_time', 'is_random_question', 'is_random_option', 'question_bank_id', 'questions_data']);
        });
    }
};
