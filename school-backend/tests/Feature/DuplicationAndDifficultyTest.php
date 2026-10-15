<?php

namespace Tests\Feature;

use App\Models\ClassModel;
use App\Models\ClassName;
use App\Models\Course;
use App\Models\Material;
use App\Models\Meet;
use App\Models\MeetAttendance;
use App\Models\QuestionBank;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DuplicationAndDifficultyTest extends TestCase
{
    protected User $adminUser;
    protected string $adminToken;
    protected User $teacherUser;
    protected Teacher $teacher;
    protected string $teacherToken;
    protected Subject $subject;
    protected ClassModel $class1;
    protected ClassModel $class2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1], ['name' => 'admin', 'display_name' => 'Administrator']);
        Role::firstOrCreate(['id' => 2], ['name' => 'teacher', 'display_name' => 'Guru']);
        Role::firstOrCreate(['id' => 3], ['name' => 'student', 'display_name' => 'Siswa']);

        // Admin User
        $this->adminUser = User::firstOrCreate(
            ['username' => 'admin_test_iter4'],
            ['name' => 'Admin Test Iter 4', 'email' => 'admin_iter4@example.com', 'password' => bcrypt('password'), 'role_id' => 1]
        );
        $this->adminToken = $this->adminUser->createToken('admin')->plainTextToken;

        // Teacher & User
        $this->teacher = Teacher::firstOrCreate(
            ['nip' => '99887766'],
            ['name' => 'Guru Pengampu Test', 'gender' => 'male', 'email' => 'guru_iter4@example.com']
        );
        $this->teacherUser = User::firstOrCreate(
            ['username' => '99887766'],
            ['name' => 'Guru Pengampu Test', 'email' => 'guru_iter4@example.com', 'password' => bcrypt('password'), 'role_id' => 2]
        );
        $this->teacherToken = $this->teacherUser->createToken('teacher')->plainTextToken;

        // Subject
        $this->subject = Subject::firstOrCreate(
            ['code' => 'TEST_MAPEL_4'],
            ['name' => 'Mapel Uji Iterasi 4']
        );

        // Classes (Rombels)
        $semester = Semester::first() ?? Semester::create(['name' => 'Ganjil 2026/2027', 'is_active' => true]);
        $cn1 = ClassName::firstOrCreate(['name' => 'X-TEST-A'], ['grade_level' => '10']);
        $cn2 = ClassName::firstOrCreate(['name' => 'X-TEST-B'], ['grade_level' => '10']);

        $this->class1 = ClassModel::firstOrCreate(
            ['class_name_id' => $cn1->id, 'semester_id' => $semester->id],
            ['capacity' => 36]
        );
        $this->class2 = ClassModel::firstOrCreate(
            ['class_name_id' => $cn2->id, 'semester_id' => $semester->id],
            ['capacity' => 36]
        );
    }

    public function test_duplicate_meet_copies_materials_and_quizzes_without_submissions_or_attendances(): void
    {
        $course = Course::create([
            'class_id'     => $this->class1->id,
            'subject_id'   => $this->subject->id,
            'teacher_id'   => $this->teacher->id,
            'credit_hours' => 2,
        ]);

        $meet = Meet::create([
            'course_id'      => $course->id,
            'meeting_number' => 1,
            'title'          => 'Pertemuan Pengenalan Konsep',
            'type'           => 'offline',
            'description'    => 'Deskripsi pengantar materi.',
        ]);

        $material = Material::create([
            'meet_id'   => $meet->id,
            'title'     => 'Slide PPT Pengantar',
            'type'      => 'file',
            'file_path' => 'materials/dummy_slide.pdf',
        ]);

        $quiz = Quiz::create([
            'meet_id'          => $meet->id,
            'title'            => 'Kuis Uji Coba 1',
            'access_token'     => 'TOKEN1',
            'duration_minutes' => 30,
        ]);

        $student = Student::firstOrCreate(
            ['nis' => '88880001'],
            ['nisn' => '0088880001', 'name' => 'Siswa Test Submission', 'gender' => 'male', 'email' => 'siswa_sub@example.com']
        );

        // Buat submission dan presensi pada pertemuan sumber
        QuizSubmission::create([
            'quiz_id'      => $quiz->id,
            'student_id'   => $student->id,
            'started_at'   => now(),
            'submitted_at' => now(),
            'score'        => 90,
        ]);

        MeetAttendance::create([
            'meet_id'     => $meet->id,
            'student_id'  => $student->id,
            'status'      => 'H',
            'recorded_by' => $this->teacher->id,
        ]);

        // Eksekusi Duplikasi Meet
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->postJson("/api/meets/{$meet->id}/duplicate");

        $res->assertStatus(201);
        $newMeetId = $res->json('data.id');

        $newMeet = Meet::with(['materials', 'quizzes.submissions', 'attendances'])->find($newMeetId);
        $this->assertNotNull($newMeet);
        $this->assertEquals(2, $newMeet->meeting_number);
        $this->assertEquals('Pertemuan Pengenalan Konsep (Salinan)', $newMeet->title);

        // Verifikasi Material tersalin
        $this->assertCount(1, $newMeet->materials);
        $this->assertEquals('Slide PPT Pengantar', $newMeet->materials->first()->title);
        $this->assertEquals('materials/dummy_slide.pdf', $newMeet->materials->first()->file_path);

        // Verifikasi Quiz tersalin dengan token baru
        $this->assertCount(1, $newMeet->quizzes);
        $newQuiz = $newMeet->quizzes->first();
        $this->assertEquals('Kuis Uji Coba 1', $newQuiz->title);
        $this->assertNotEmpty($newQuiz->access_token);
        $this->assertNotEquals('TOKEN1', $newQuiz->access_token);

        // Verifikasi TIDAK ada submission atau presensi yang ikut tersalin!
        $this->assertCount(0, $newQuiz->submissions);
        $this->assertCount(0, $newMeet->attendances);

        // Cleanup
        $course->delete();
    }

    public function test_duplicate_course_copies_all_meets_and_rejects_duplicate_subject(): void
    {
        $sourceCourse = Course::create([
            'class_id'     => $this->class1->id,
            'subject_id'   => $this->subject->id,
            'teacher_id'   => $this->teacher->id,
            'credit_hours' => 3,
        ]);

        Meet::create([
            'course_id'      => $sourceCourse->id,
            'meeting_number' => 1,
            'title'          => 'Bab 1 Pertemuan 1',
            'type'           => 'offline',
        ]);
        Meet::create([
            'course_id'      => $sourceCourse->id,
            'meeting_number' => 2,
            'title'          => 'Bab 1 Pertemuan 2',
            'type'           => 'offline',
        ]);

        // Duplikat ke Rombel 2
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->postJson("/api/courses/{$sourceCourse->id}/duplicate", [
                'class_id' => $this->class2->id,
            ]);

        $res->assertStatus(201);
        $newCourseId = $res->json('data.id');

        $newCourse = Course::with('meets')->find($newCourseId);
        $this->assertNotNull($newCourse);
        $this->assertEquals($this->class2->id, $newCourse->class_id);
        $this->assertCount(2, $newCourse->meets);

        // Coba duplikat lagi ke Rombel 2 -> Harus Ditolak karena mapel sudah ada di rombel 2!
        $resDuplicateFail = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->postJson("/api/courses/{$sourceCourse->id}/duplicate", [
                'class_id' => $this->class2->id,
            ]);

        $resDuplicateFail->assertStatus(422);
        $resDuplicateFail->assertJson(['error_code' => 'course_already_exists']);

        // Cleanup
        $sourceCourse->delete();
        $newCourse->delete();
    }

    public function test_shared_material_file_not_deleted_when_one_copy_is_deleted(): void
    {
        Storage::fake('public');
        $fakeFilePath = 'materials/shared_handout.pdf';
        Storage::disk('public')->put($fakeFilePath, 'File content dummy');

        $meet = Meet::first() ?? Meet::create([
            'course_id'      => 1,
            'meeting_number' => 99,
            'title'          => 'Dummy Meet',
            'type'           => 'offline',
        ]);

        $mat1 = Material::create([
            'meet_id'   => $meet->id,
            'title'     => 'Materi Asli',
            'type'      => 'file',
            'file_path' => $fakeFilePath,
        ]);

        $mat2 = Material::create([
            'meet_id'   => $meet->id,
            'title'     => 'Materi Salinan',
            'type'      => 'file',
            'file_path' => $fakeFilePath,
        ]);

        // Hapus salah satu materi
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/materials/{$mat1->id}");

        $res->assertStatus(200);

        // File fisik di storage HARUS tetap ada karena masih dipakai oleh $mat2!
        $this->assertTrue(Storage::disk('public')->exists($fakeFilePath));

        // Hapus salinan terakhir
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/materials/{$mat2->id}");

        // Sekarang file fisik baru benar-benar dihapus
        $this->assertFalse(Storage::disk('public')->exists($fakeFilePath));
    }

    public function test_question_bank_difficulty_filter_and_summary(): void
    {
        // Buat Question Bank dengan tingkat kesulitan 'hard'
        $bank = QuestionBank::create([
            'teacher_id'  => $this->teacher->id,
            'subject_id'  => $this->subject->id,
            'grade_level' => '10',
            'difficulty'  => 'hard',
            'title'       => 'Paket Soal Tantangan Olimpiade',
            'is_public'   => true,
        ]);

        // Tambah butir soal: 1 easy, 2 medium, 3 hard
        QuestionBankItem::create([
            'question_bank_id' => $bank->id,
            'question'         => 'Soal Mudah 1',
            'type'             => 'pg',
            'difficulty'       => 'easy',
            'weight'           => 10,
        ]);
        QuestionBankItem::create([
            'question_bank_id' => $bank->id,
            'question'         => 'Soal Sedang 1',
            'type'             => 'pg',
            'difficulty'       => 'medium',
            'weight'           => 15,
        ]);
        QuestionBankItem::create([
            'question_bank_id' => $bank->id,
            'question'         => 'Soal Sedang 2',
            'type'             => 'pg',
            'difficulty'       => 'medium',
            'weight'           => 15,
        ]);
        QuestionBankItem::create([
            'question_bank_id' => $bank->id,
            'question'         => 'Soal Sulit 1',
            'type'             => 'pg',
            'difficulty'       => 'hard',
            'weight'           => 20,
        ]);

        // Filter daftar bank soal berdasarkan ?difficulty=hard
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->getJson('/api/question-banks?difficulty=hard');

        $res->assertStatus(200);
        $banks = $res->json('data.data') ?? $res->json('data');

        $found = collect($banks)->firstWhere('id', $bank->id);
        $this->assertNotNull($found);
        $this->assertEquals('hard', $found['difficulty']);

        // Verifikasi difficulty_summary
        $summary = $found['difficulty_summary'];
        $this->assertEquals(4, $summary['total']);
        $this->assertEquals(1, $summary['easy']);
        $this->assertEquals(2, $summary['medium']);
        $this->assertEquals(1, $summary['hard']);

        // Filter ?difficulty=easy tidak boleh menemukan bank ini
        $resEasy = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->getJson('/api/question-banks?difficulty=easy');
        $banksEasy = $resEasy->json('data.data') ?? $resEasy->json('data');
        $this->assertNull(collect($banksEasy)->firstWhere('id', $bank->id));

        // Cleanup
        $bank->items()->delete();
        $bank->delete();
    }
}
