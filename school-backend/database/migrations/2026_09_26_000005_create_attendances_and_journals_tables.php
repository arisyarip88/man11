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
        // 1. Presensi Kehadiran Siswa per Pertemuan / Sesi (Meet Attendance)
        Schema::create('meet_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_id')->constrained('meets')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['H', 'S', 'I', 'A'])->default('H'); // Hadir, Sakit, Izin, Alpa
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meet_id', 'student_id']);
        });

        // 2. Jurnal Mengajar Harian Guru (Teaching Log) & Verifikasi Kurikulum
        Schema::create('teacher_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('meet_id')->nullable()->constrained('meets')->nullOnDelete();
            $table->date('date');
            $table->string('session_time', 100); // Contoh: 'Jam ke 1 - 2 (07.00 - 08.20)'
            $table->string('topic');
            $table->text('activity_summary');
            $table->text('notes')->nullable();
            $table->json('attendance_stats')->nullable(); // { "hadir": 34, "sakit": 1, "izin": 1, "alpa": 0 }
            $table->string('photo_evidence')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('approval_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_journals');
        Schema::dropIfExists('meet_attendances');
    }
};
