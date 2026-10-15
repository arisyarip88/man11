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
        // 1. Sesi KBM Tatap Muka Terpisah dari Modul Pembelajaran
        Schema::create('kbm_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('active_meet_id')->nullable()->constrained('meets')->nullOnDelete();
            $table->enum('status', ['scheduled', 'open', 'completed'])->default('scheduled');
            $table->string('qr_token', 64)->nullable();
            $table->boolean('allow_self_checkin')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'date']);
            $table->index(['teacher_id', 'date']);
        });

        // 2. Data Presensi Siswa per Sesi KBM
        Schema::create('session_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('kbm_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['H', 'S', 'I', 'A'])->default('H'); // Hadir, Sakit, Izin, Alpa
            $table->enum('method', ['self_click', 'qr_scan', 'manual_teacher'])->default('self_click');
            $table->string('notes')->nullable();
            $table->timestamp('checkin_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_attendances');
        Schema::dropIfExists('kbm_sessions');
    }
};
