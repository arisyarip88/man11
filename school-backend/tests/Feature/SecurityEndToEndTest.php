<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CbtController;
use App\Models\ClassName;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Meet;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherJournal;
use App\Models\User;
use App\Models\UserSession;
use App\Services\QuestionScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1], ['name' => 'admin', 'display_name' => 'Administrator']);
        Role::firstOrCreate(['id' => 2], ['name' => 'teacher', 'display_name' => 'Guru']);
        Role::firstOrCreate(['id' => 3], ['name' => 'student', 'display_name' => 'Siswa']);

        RateLimiter::clear('cbt-token:1:1');
    }

    /**
     * SEC-AUTH-00: Registrasi publik ditutup/dinonaktifkan (403 Forbidden).
     */
    public function test_public_registration_is_disabled(): void
    {
        $response = $this->postJson('/api/register', [
            'username' => 'self_reg_user',
            'email'    => 'self_reg@example.com',
            'password' => 'secret12345',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'error_code' => 'registration_disabled',
        ]);
    }

    /**
     * SEC-AUTH-01: Brute force login dicegat oleh rate limiter (429 Too Many Requests).
     */
    public function test_login_rate_limiting_enforced_against_brute_force(): void
    {
        $username = 'brute_force_target';
        User::create([
            'name'     => 'Brute Target',
            'username' => $username,
            'email'    => 'target@example.com',
            'password' => Hash::make('secret123'),
            'role_id'  => 3,
        ]);

        // Lakukan 5 percobaan salah
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', [
                'login'    => $username,
                'password' => 'wrong_pass_' . $i,
            ]);
            $response->assertStatus(422);
        }

        // Percobaan ke-6 harus ditolak oleh Rate Limiter (429)
        $rateLimitedResponse = $this->postJson('/api/login', [
            'login'    => $username,
            'password' => 'wrong_pass_6',
        ]);

        $rateLimitedResponse->assertStatus(429);
        $rateLimitedResponse->assertJsonFragment([
            'error_code' => 'too_many_attempts',
        ]);
    }

    /**
     * SEC-AUTH-02: Payload SQL Injection pada form login ditolak secara aman tanpa database error leak.
     */
    public function test_sql_injection_payload_in_login_rejected_securely(): void
    {
        $payloads = [
            "' OR '1'='1",
            "admin' --",
            "' UNION SELECT * FROM users --",
        ];

        foreach ($payloads as $payload) {
            $response = $this->postJson('/api/login', [
                'login'    => $payload,
                'password' => 'any_password',
            ]);

            $response->assertStatus(422);
            $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
            $this->assertStringNotContainsString('syntax error', $response->getContent());
        }
    }

    /**
     * SEC-AUTH-03: Single Device Session - Login perangkat kedua mencabut token perangkat pertama.
     */
    public function test_single_device_enforcement_and_session_replacement(): void
    {
        $studentUser = User::create([
            'name'     => 'Siswa Single Device',
            'username' => 'siswa_device_01',
            'email'    => 'siswa_dev1@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => 3,
        ]);

        Student::create([
            'nis'    => 'siswa_device_01',
            'nisn'   => '1122334455',
            'name'   => 'Siswa Single Device',
            'gender' => 'male',
            'email'  => 'siswa_dev1@example.com',
            'status' => 'active',
        ]);

        // Login Device 1
        $login1 = $this->postJson('/api/login', [
            'login'    => 'siswa_device_01',
            'password' => 'password123',
        ], ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0']);

        $login1->assertStatus(200);
        $tokenDevice1 = $login1->json('token');
        $this->assertNotEmpty($tokenDevice1);

        // Verifikasi token 1 aktif
        $me1 = $this->withHeader('Authorization', 'Bearer ' . $tokenDevice1)->getJson('/api/me');
        $me1->assertStatus(200);

        // Login Device 2
        $login2 = $this->postJson('/api/login', [
            'login'    => 'siswa_device_01',
            'password' => 'password123',
        ], ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0) Safari/604.1']);

        $login2->assertStatus(200);
        $tokenDevice2 = $login2->json('token');
        $this->assertNotEquals($tokenDevice1, $tokenDevice2);

        // Reset memory guard cache
        $this->app['auth']->forgetGuards();

        // Token Device 1 sekarang ditolak dengan session_replaced (401)
        $meOld = $this->withHeader('Authorization', 'Bearer ' . $tokenDevice1)->getJson('/api/me');
        $meOld->assertStatus(401);
        $this->assertEquals('session_replaced', $meOld->json('error_code'));

        // Token Device 2 tetap aktif
        $meNew = $this->withHeader('Authorization', 'Bearer ' . $tokenDevice2)->getJson('/api/me');
        $meNew->assertStatus(200);
    }

    /**
     * SEC-AUTH-04: Sesi ujian aktif mencegah siswa login di perangkat lain (409 Conflict).
     */
    public function test_exam_in_progress_blocks_login_on_second_device(): void
    {
        $studentUser = User::create([
            'name'     => 'Siswa CBT Lock',
            'username' => 'siswa_cbt_lock',
            'email'    => 'cbt_lock@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => 3,
        ]);

        $student = Student::create([
            'nis'    => 'siswa_cbt_lock',
            'nisn'   => '9988776655',
            'name'   => 'Siswa CBT Lock',
            'gender' => 'male',
            'email'  => 'cbt_lock@example.com',
            'status' => 'active',
        ]);

        // Simulasikan submission ujian aktif yang sedang dikerjakan
        $quiz = Quiz::create([
            'title'            => 'Kuis Ujian Sedang Berjalan',
            'quiz_type'        => 'multiple_choice',
            'duration_minutes' => 60,
            'questions_data'   => [],
        ]);

        QuizSubmission::create([
            'quiz_id'         => $quiz->id,
            'student_id'      => $student->id,
            'started_at'      => now(),
            'submitted_at'    => null, // Belum selesai!
            'status'          => 'in_progress',
            'violation_count' => 0,
        ]);

        // Percobaan login di perangkat kedua
        $attempt = $this->postJson('/api/login', [
            'login'    => 'siswa_cbt_lock',
            'password' => 'password123',
        ]);

        $attempt->assertStatus(409);
        $attempt->assertJsonFragment([
            'error_code' => 'exam_in_progress',
        ]);
    }

    /**
     * SEC-RBAC-01: Siswa ditolak saat mengakses endpoint manajemen pengguna admin (403 Forbidden).
     */
    public function test_student_role_cannot_access_admin_user_management(): void
    {
        $studentUser = User::create([
            'name'     => 'Siswa RBAC Test',
            'username' => 'siswa_rbac',
            'email'    => 'siswa_rbac@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => 3,
        ]);

        $token = $studentUser->createToken('auth_token')->plainTextToken;

        // Siswa mencoba membaca daftar user
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users');

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'error_code' => 'forbidden',
        ]);

        // Siswa mencoba membuat user baru
        $postResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/users', [
                'name'     => 'Hacker User',
                'username' => 'hacker',
                'email'    => 'hacker@example.com',
                'password' => 'password',
                'role_id'  => 1,
            ]);

        $postResponse->assertStatus(403);
    }

    /**
     * SEC-RBAC-02: Siswa ditolak saat mencoba mereset sesi akun lain (403 Forbidden).
     */
    public function test_student_role_cannot_reset_sessions(): void
    {
        $studentUser = User::create([
            'name'     => 'Siswa RBAC 2',
            'username' => 'siswa_rbac_2',
            'email'    => 'siswa_rbac2@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => 3,
        ]);

        $token = $studentUser->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/users/1/reset-sessions');

        $response->assertStatus(403);
    }

    /**
     * SEC-RBAC-03: Siswa ditolak saat memverifikasi jurnal guru (403 Forbidden).
     */
    public function test_student_role_cannot_verify_teacher_journal(): void
    {
        $studentUser = User::create([
            'name'     => 'Siswa RBAC 3',
            'username' => 'siswa_rbac_3',
            'email'    => 'siswa_rbac3@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => 3,
        ]);

        $token = $studentUser->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/teacher-journals/1/verify', [
                'approval_status' => 'approved',
            ]);

        $response->assertStatus(403);
    }

    /**
     * SEC-RBAC-04: Administrator sah diizinkan mengakses endpoint admin.
     */
    public function test_admin_can_access_admin_user_management(): void
    {
        $admin = User::create([
            'name'     => 'Admin Real',
            'username' => 'admin_real',
            'email'    => 'admin_real@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => 1,
        ]);

        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users');

        $response->assertStatus(200);
    }

    /**
     * SEC-SES-04: Security Headers terpasang pada seluruh response API.
     */
    public function test_security_headers_present_on_api_responses(): void
    {
        $response = $this->getJson('/api/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * SEC-CBT-01: Sanitasi soal CBT tidak membocorkan kunci jawaban atau pembahasan ke siswa.
     */
    public function test_cbt_question_sanitization_does_not_leak_answers(): void
    {
        $rawQuestion = [
            'number'         => 1,
            'type'           => 'pg',
            'question'       => 'Berapa hasil dari 5 + 5?',
            'correct_answer' => 'B',
            'explanation'    => '5 + 5 adalah 10.',
            'options'        => [
                ['key' => 'A', 'text' => '9', 'is_correct' => false],
                ['key' => 'B', 'text' => '10', 'is_correct' => true],
                ['key' => 'C', 'text' => '11', 'is_correct' => false],
            ],
            'weight'         => 2,
            'difficulty'     => 'easy',
        ];

        $sanitized = QuestionScorer::sanitize($rawQuestion, 'student-seed-123');

        // Pastikan tidak ada kunci jawaban atau pembahasan
        $this->assertArrayNotHasKey('correct_answer', $sanitized);
        $this->assertArrayNotHasKey('explanation', $sanitized);

        foreach ($sanitized['options'] as $opt) {
            $this->assertArrayNotHasKey('is_correct', $opt);
            $this->assertArrayHasKey('key', $opt);
            $this->assertArrayHasKey('text', $opt);
        }
    }

    /**
     * SEC-TERM-01: Post-Submission Tampering - Jawaban ujian terkunci dan tidak bisa ditimpa pasca selesai.
     */
    public function test_cbt_post_submission_answers_cannot_be_overwritten(): void
    {
        $studentUser = User::create([
            'name'     => 'Siswa Finish Lock',
            'username' => 'siswa_finish_lock',
            'email'    => 'finish_lock@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => 3,
        ]);

        $student = Student::create([
            'nis'    => 'siswa_finish_lock',
            'nisn'   => '5544332211',
            'name'   => 'Siswa Finish Lock',
            'gender' => 'male',
            'email'  => 'finish_lock@example.com',
            'status' => 'active',
        ]);

        $token = $studentUser->createToken('auth_token')->plainTextToken;

        // Buat dummy Quiz sederhana
        $quiz = Quiz::create([
            'title'            => 'Kuis Ujian Pasca Selesai',
            'quiz_type'        => 'multiple_choice',
            'duration_minutes' => 60,
            'questions_data'   => [],
        ]);

        // Simulasikan submission yang SUDAH diselesaikan
        QuizSubmission::create([
            'quiz_id'         => $quiz->id,
            'student_id'      => $student->id,
            'started_at'      => now()->subMinutes(30),
            'submitted_at'    => now()->subMinutes(5),
            'status'          => 'submitted',
            'score'           => 85,
            'violation_count' => 0,
        ]);

        // Siswa mencoba mengirimkan jawaban baru ke /save-progress
        $saveAttempt = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/quizzes/{$quiz->id}/cbt/save-progress", [
                'answers' => ['1' => 'B'],
            ]);

        $saveAttempt->assertStatus(422);

        // Siswa mencoba memanggil /finish ulang
        $finishAttempt = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/quizzes/{$quiz->id}/cbt/finish", [
                'answers' => ['1' => 'A'],
            ]);

        $finishAttempt->assertStatus(422);
        $finishAttempt->assertJsonFragment([
            'error_code' => 'already_submitted',
        ]);
    }

    /**
     * SEC-TERM-03: Logout berhasil mencabut token Sanctum dan menghentikan sesi aktif.
     */
    public function test_logout_invalidates_token_and_session(): void
    {
        $user = User::create([
            'name'     => 'User Logout Test',
            'username' => 'logout_test',
            'email'    => 'logout@example.com',
            'password' => Hash::make('password123'),
            'role_id'  => 3,
        ]);

        $login = $this->postJson('/api/login', [
            'login'    => 'logout_test',
            'password' => 'password123',
        ]);

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        // Panggil Logout
        $logout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $logout->assertStatus(200);

        // Bersihkan state memori auth guard
        $this->app['auth']->forgetGuards();

        // Token sudah dihapus dari tabel personal_access_tokens
        $this->assertEquals(0, $user->tokens()->count());

        // Request dengan token tersebut sekarang 401
        $me = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $me->assertStatus(401);
    }
}
