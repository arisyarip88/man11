<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Semesters (Tahun Ajaran & Semester)
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20); // Contoh: 2025/2026
            $table->enum('semester_type', ['odd', 'even']); // Ganjil / Genap
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // 2. Class Names (Master Level & Nama Kelas)
        Schema::create('class_names', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // Contoh: X IPA 1, 8B, XII TKJ 2
            $table->enum('grade_level', ['7', '8', '9', '10', '11', '12']);
            $table->string('major', 50)->nullable(); // Khusus SMA/SMK (IPA, IPS, TKJ)
            $table->timestamps();
        });

        // 3. Teachers (Data Guru)
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->unique();
            $table->string('nik', 16)->unique()->nullable();
            $table->string('name');
            $table->enum('gender', ['male', 'female']);
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Students (Data Siswa)
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('nisn', 10)->unique();
            $table->string('nis', 20)->unique()->nullable();
            $table->string('name');
            $table->enum('gender', ['male', 'female']);
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'graduated', 'transferred'])->default('active');
            $table->timestamps();
        });

        // 5. Classes (Rombel / Penetapan Kelas Per Semester)
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_name_id')->constrained()->cascadeOnDelete();
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->integer('capacity')->default(36);
            $table->string('room_number', 20)->nullable();
            $table->timestamps();
        });

        // 6. Class_Student (Pivot Penempatan Siswa di Kelas)
        Schema::create('class_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_id', 'student_id']);
        });

        // 7. Subjects (Master Mata Pelajaran)
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // Contoh: MAT-X
            $table->string('name'); // Contoh: Matematika Wajib
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 8. Courses (Pengajaran Mapel oleh Guru di Kelas Tertentu)
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->integer('credit_hours')->default(2); // Alokasi JP (Jam Pelajaran)
            $table->timestamps();
        });

        // 9. Meets (Pertemuan / Sesi Pembelajaran)
        Schema::create('meets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('meeting_number'); // Pertemuan ke-1, 2, dst.
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->enum('type', ['online', 'offline'])->default('offline');
            $table->string('meeting_link')->nullable(); // Link Google Meet/Zoom jika online
            $table->timestamps();
        });

        // 10. Materials (Materi / Modul Ajar)
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['file', 'video', 'link', 'text'])->default('file');
            $table->longText('content')->nullable(); // Teks ringkasan materi
            $table->string('file_path')->nullable(); // Path penyimpanan file (PDF/PPT)
            $table->string('external_url')->nullable(); // Link Youtube/Drive
            $table->timestamps();
        });

        // 11. Quizzes (Kuis / Tugas Pertemuan)
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->enum('quiz_type', ['assignment', 'multiple_choice', 'essay'])->default('assignment');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->integer('max_score')->default(100);
            $table->integer('duration_minutes')->nullable(); // Durasi pengerjaan jika ada
            $table->timestamps();
        });

        // 12. Quiz Submissions (Pengumpulan Tugas / Hasil Kuis Siswa)
        Schema::create('quiz_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('submission_text')->nullable();
            $table->string('file_path')->nullable();
            $table->dateTime('submitted_at');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->enum('status', ['submitted', 'graded', 'late'])->default('submitted');
            $table->timestamps();

            $table->unique(['quiz_id', 'student_id']);
        });

        // 13. Discussions (Forum Diskusi Pertemuan)
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('discussions')->cascadeOnDelete(); // Untuk balasan komentar (nested thread)
            $table->text('message');
            $table->string('attachment_file')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussions');
        Schema::dropIfExists('quiz_submissions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('meets');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('class_student');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('students');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('class_names');
        Schema::dropIfExists('semesters');
    }
};