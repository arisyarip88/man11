<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ClassModel;
use App\Models\ClassName;
use App\Models\Course;
use App\Models\Meet;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Tests\TestCase;

class ChaptersAndScheduleNavigationTest extends TestCase
{
    protected User $adminUser;
    protected string $adminToken;
    protected User $teacherUser;
    protected Teacher $teacher;
    protected string $teacherToken;
    protected Course $course;
    protected ClassModel $rombel1;
    protected ClassModel $rombel2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1], ['name' => 'admin', 'display_name' => 'Administrator']);
        Role::firstOrCreate(['id' => 2], ['name' => 'teacher', 'display_name' => 'Guru']);

        // Admin User
        $this->adminUser = User::firstOrCreate(
            ['username' => 'admin_test_iter5'],
            ['name' => 'Admin Test Iter 5', 'email' => 'admin_iter5@example.com', 'password' => bcrypt('password'), 'role_id' => 1]
        );
        $this->adminToken = $this->adminUser->createToken('admin')->plainTextToken;

        // Teacher & User
        $this->teacher = Teacher::firstOrCreate(
            ['nip' => '55667788'],
            ['name' => 'Guru Bab Test', 'gender' => 'male', 'email' => 'guru_iter5@example.com']
        );
        $this->teacherUser = User::firstOrCreate(
            ['username' => '55667788'],
            ['name' => 'Guru Bab Test', 'email' => 'guru_iter5@example.com', 'password' => bcrypt('password'), 'role_id' => 2]
        );
        $this->teacherToken = $this->teacherUser->createToken('teacher')->plainTextToken;

        $semester = Semester::first() ?? Semester::create([
            'academic_year' => '2026/2027',
            'semester_type' => 'odd',
            'start_date'    => '2026-07-01',
            'end_date'      => '2026-12-31',
            'is_active'     => true,
        ]);
        $cn1 = ClassName::firstOrCreate(['name' => 'XI-MIPA-5']);
        $cn2 = ClassName::firstOrCreate(['name' => 'XI-MIPA-6']);

        $this->rombel1 = ClassModel::firstOrCreate(
            ['semester_id' => $semester->id, 'class_name_id' => $cn1->id],
            ['room_number' => 'R205']
        );

        $this->rombel2 = ClassModel::firstOrCreate(
            ['semester_id' => $semester->id, 'class_name_id' => $cn2->id],
            ['room_number' => 'R206']
        );

        $subject = Subject::firstOrCreate(['code' => 'BIO_MOL_5'], ['name' => 'Biologi Sel Molekuler']);

        // Pastikan course tidak duplikat
        Course::where('class_id', $this->rombel1->id)->where('subject_id', $subject->id)->delete();
        Course::where('class_id', $this->rombel2->id)->where('subject_id', $subject->id)->delete();

        $this->course = Course::create([
            'class_id'     => $this->rombel1->id,
            'subject_id'   => $subject->id,
            'teacher_id'   => $this->teacher->id,
            'credit_hours' => 2,
        ]);
    }

    public function test_chapters_crud_and_unassigned_meets_compatibility()
    {
        // 1. Buat pertemuan lama tanpa bab
        $meet1 = Meet::create([
            'course_id'      => $this->course->id,
            'meeting_number' => 1,
            'title'          => 'Pertemuan 1 Pengenalan Sel',
            'type'           => 'offline',
        ]);

        // 2. Akses index chapters: unassigned_meets harus berisi meet1
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->getJson("/api/courses/{$this->course->id}/chapters");

        $res->assertStatus(200);
        $res->assertJsonCount(0, 'chapters');
        $this->assertTrue(collect($res->json('unassigned_meets'))->contains('id', $meet1->id));

        // 3. Buat Bab baru
        $createChapterRes = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->postJson("/api/courses/{$this->course->id}/chapters", [
                'title'       => 'Bab 1: Struktur DNA dan RNA',
                'description' => 'Membahas materi genetik',
                'order'       => 1,
            ]);

        $createChapterRes->assertStatus(201);
        $chapterId = $createChapterRes->json('data.id');
        $this->assertDatabaseHas('chapters', ['id' => $chapterId, 'title' => 'Bab 1: Struktur DNA dan RNA']);

        // 4. Assign meet1 ke Bab 1
        $assignRes = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->postJson("/api/chapters/{$chapterId}/assign-meets", [
                'meet_ids' => [$meet1->id],
            ]);
        $assignRes->assertStatus(200);

        // Pastikan meet1 sekarang memiliki chapter_id
        $this->assertDatabaseHas('meets', [
            'id'         => $meet1->id,
            'chapter_id' => $chapterId,
        ]);

        // 5. Cek lagi index chapters: chapter 1 harus berisi meet1
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->getJson("/api/courses/{$this->course->id}/chapters");
        $res2->assertStatus(200);
        $res2->assertJsonCount(1, 'chapters');
        $this->assertEquals($meet1->id, $res2->json('chapters.0.meets.0.id'));

        // 6. Update Bab
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->putJson("/api/chapters/{$chapterId}", [
                'title' => 'Bab 1: Genetika & Struktur DNA (Revisi)',
            ]);
        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('chapters', ['id' => $chapterId, 'title' => 'Bab 1: Genetika & Struktur DNA (Revisi)']);

        // 7. Hapus Bab: meet1 harus menjadi chapter_id = null tanpa terhapus
        $deleteRes = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->deleteJson("/api/chapters/{$chapterId}");
        $deleteRes->assertStatus(200);

        $this->assertDatabaseMissing('chapters', ['id' => $chapterId]);
        $this->assertDatabaseHas('meets', [
            'id'         => $meet1->id,
            'chapter_id' => null,
        ]);
    }

    public function test_schedule_current_meet_resolves_today_upcoming_or_fallback()
    {
        Schedule::where('course_id', $this->course->id)->delete();
        Meet::where('course_id', $this->course->id)->delete();

        $schedule = Schedule::create([
            'course_id'   => $this->course->id,
            'day'         => 'monday',
            'start_time'  => '08:00',
            'end_time'    => '09:30',
            'room_number' => 'R205',
        ]);

        $chapter = Chapter::create([
            'course_id' => $this->course->id,
            'title'     => 'Bab 2: Pembelahan Sel',
            'order'     => 1,
        ]);

        // Kasus 1: Belum ada pertemuan sama sekali -> status: none
        $resNone = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->getJson("/api/schedules/{$schedule->id}/current-meet");
        $resNone->assertStatus(200);
        $this->assertEquals('none', $resNone->json('status'));

        // Kasus 2: Hanya ada pertemuan fallback (di masa lalu)
        $pastMeet = Meet::create([
            'course_id'      => $this->course->id,
            'chapter_id'     => $chapter->id,
            'meeting_number' => 1,
            'title'          => 'Pertemuan Silabus Lampau',
            'type'           => 'offline',
            'start_time'     => now()->subDays(5)->format('Y-m-d H:i:s'),
        ]);

        $resFallback = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->getJson("/api/schedules/{$schedule->id}/current-meet");
        $resFallback->assertStatus(200);
        $this->assertEquals('fallback', $resFallback->json('status'));
        $this->assertEquals($pastMeet->id, $resFallback->json('data.id'));

        // Kasus 3: Ada pertemuan hari ini
        $todayMeet = Meet::create([
            'course_id'      => $this->course->id,
            'chapter_id'     => $chapter->id,
            'meeting_number' => 2,
            'title'          => 'Pertemuan Mitosis Hari Ini',
            'type'           => 'offline',
            'start_time'     => now()->hour(8)->minute(30)->format('Y-m-d H:i:s'),
        ]);

        $resToday = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->getJson("/api/schedules/{$schedule->id}/current-meet");
        $resToday->assertStatus(200);
        $this->assertEquals('today', $resToday->json('status'));
        $this->assertEquals($todayMeet->id, $resToday->json('data.id'));
        $this->assertEquals('Bab 2: Pembelahan Sel', $resToday->json('data.chapter.title'));
    }

    public function test_duplicate_course_copies_chapters_and_remappes_meets_chapter_ids()
    {
        // Bersihkan course target jika ada
        Course::where('class_id', $this->rombel2->id)->where('subject_id', $this->course->subject_id)->delete();

        // 1. Siapkan 2 chapter di source course
        $chap1 = Chapter::create([
            'course_id' => $this->course->id,
            'title'     => 'Bab 1: Asal Mula Kehidupan',
            'order'     => 1,
        ]);
        $chap2 = Chapter::create([
            'course_id' => $this->course->id,
            'title'     => 'Bab 2: Evolusi',
            'order'     => 2,
        ]);

        // 2. Siapkan 3 meet (2 di chap1, 1 di chap2)
        $meet1 = Meet::create([
            'course_id'      => $this->course->id,
            'chapter_id'     => $chap1->id,
            'meeting_number' => 1,
            'title'          => 'Pertemuan 1',
            'type'           => 'offline',
        ]);
        $meet2 = Meet::create([
            'course_id'      => $this->course->id,
            'chapter_id'     => $chap1->id,
            'meeting_number' => 2,
            'title'          => 'Pertemuan 2',
            'type'           => 'offline',
        ]);
        $meet3 = Meet::create([
            'course_id'      => $this->course->id,
            'chapter_id'     => $chap2->id,
            'meeting_number' => 3,
            'title'          => 'Pertemuan 3',
            'type'           => 'offline',
        ]);

        // 3. Eksekusi duplikasi
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->teacherToken)
            ->postJson("/api/courses/{$this->course->id}/duplicate", [
                'class_id' => $this->rombel2->id,
            ]);

        $res->assertStatus(201);
        $newCourseId = $res->json('data.id');

        // 4. Verifikasi Chapters pada target course
        $newChapters = Chapter::where('course_id', $newCourseId)->orderBy('order', 'asc')->get();
        $this->assertCount(2, $newChapters);
        $this->assertEquals('Bab 1: Asal Mula Kehidupan', $newChapters[0]->title);
        $this->assertEquals('Bab 2: Evolusi', $newChapters[1]->title);
        $this->assertNotEquals($chap1->id, $newChapters[0]->id);
        $this->assertNotEquals($chap2->id, $newChapters[1]->id);

        // 5. Verifikasi Meets pada target course memiliki chapter_id yang terpetakan ke new chapters
        $newMeets = Meet::where('course_id', $newCourseId)->orderBy('meeting_number', 'asc')->get();
        $this->assertCount(3, $newMeets);

        // Meet 1 dan 2 harus terhubung ke newChapters[0]
        $this->assertEquals($newChapters[0]->id, $newMeets[0]->chapter_id);
        $this->assertEquals($newChapters[0]->id, $newMeets[1]->chapter_id);

        // Meet 3 harus terhubung ke newChapters[1]
        $this->assertEquals($newChapters[1]->id, $newMeets[2]->chapter_id);
    }
}
