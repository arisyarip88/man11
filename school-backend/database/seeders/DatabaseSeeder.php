<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Semester;
use App\Models\ClassName;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Course;
use App\Models\Meet;
use App\Models\Material;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\Discussion;
use Carbon\Carbon;
class DatabaseSeeder extends Seeder
{
  

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Data Semester
        $semester = Semester::create([
            'academic_year' => '2025/2026',
            'semester_type' => 'odd',
            'start_date' => '2025-07-15',
            'end_date' => '2025-12-20',
            'is_active' => true,
        ]);

        // 2. Master Kelas
        $className = ClassName::create([
            'name' => 'X IPA 1',
            'grade_level' => '10',
            'major' => 'MIPA',
        ]);

        // 3. Data Guru
        $teacher = Teacher::create([
            'nip' => '198501012010011001',
            'nik' => '3201000000000001',
            'name' => 'Budi Santoso, S.Pd.',
            'gender' => 'male',
            'email' => 'budi.santoso@sekolah.sch.id',
            'phone' => '081234567890',
            'address' => 'Jl. Pendidikan No. 12',
            'is_active' => true,
        ]);

        // 4. Data Siswa
        $student = Student::create([
            'nisn' => '0051234567',
            'nis' => '252610001',
            'name' => 'Ahmad Rizky',
            'gender' => 'male',
            'email' => 'ahmad.rizky@siswa.sch.id',
            'phone' => '089876543210',
            'birth_date' => '2009-04-12',
            'birth_place' => 'Jakarta',
            'address' => 'Jl. Merdeka No. 45',
            'status' => 'active',
        ]);

        // 5. Penetapan Kelas (Rombel) & Enrolment Siswa
        $class = ClassModel::create([
            'semester_id' => $semester->id,
            'class_name_id' => $className->id,
            'homeroom_teacher_id' => $teacher->id,
            'capacity' => 36,
            'room_number' => 'R.101',
        ]);

        $class->students()->attach($student->id);

        // 6. Mata Pelajaran & Pengajaran (Course)
        $subject = Subject::create([
            'code' => 'MAT-X',
            'name' => 'Matematika Wajib',
            'description' => 'Mata pelajaran Matematika untuk kelas X MIPA',
        ]);

        $course = Course::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'credit_hours' => 3,
        ]);

        // 7. Sesi Pertemuan
        $meet = Meet::create([
            'course_id' => $course->id,
            'meeting_number' => 1,
            'title' => 'Pertemuan 1: Persamaan Linier',
            'description' => 'Pengenalan konsep dasar persamaan linier dua variabel',
            'start_time' => Carbon::now(),
            'end_time' => Carbon::now()->addHours(2),
            'type' => 'offline',
        ]);

        // 8. Materi Ajar
        Material::create([
            'meet_id' => $meet->id,
            'title' => 'Modul Bab 1: Persamaan Linier.pdf',
            'type' => 'file',
            'content' => 'Ringkasan rumus dan latihan soal bab 1.',
            'file_path' => 'materials/modul-bab-1.pdf',
        ]);

        // 9. Kuis / Tugas
        $quiz = Quiz::create([
            'meet_id' => $meet->id,
            'title' => 'Tugas Mandiri 1',
            'instructions' => 'Kerjakan soal latihan nomor 1 sampai 5 pada halaman 12.',
            'quiz_type' => 'assignment',
            'start_time' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(3),
            'max_score' => 100,
        ]);

        // 10. Pengumpulan Tugas Siswa
        QuizSubmission::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'submission_text' => 'Berikut adalah hasil pengerjaan tugas saya Pak.',
            'file_path' => 'submissions/tugas1_ahmad_rizky.pdf',
            'submitted_at' => Carbon::now(),
            'score' => 95.00,
            'teacher_feedback' => 'Pengerjaan sangat baik dan rapi.',
            'status' => 'graded',
        ]);

        // 11. Forum Diskusi
        $parentDiscussion = Discussion::create([
            'meet_id' => $meet->id,
            'student_id' => $student->id,
            'message' => 'Pak Budi, apakah soal nomor 4 menggunakan metode substitusi?',
        ]);

        Discussion::create([
            'meet_id' => $meet->id,
            'teacher_id' => $teacher->id,
            'parent_id' => $parentDiscussion->id,
            'message' => 'Bisa menggunakan substitusi maupun eliminasi, Ahmad.',
        ]);
    }
}