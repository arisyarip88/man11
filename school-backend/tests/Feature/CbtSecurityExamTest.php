<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CbtController;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CbtSecurityExamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('cbt-token:1:9999');
    }

    public function test_regenerate_clean_token_has_no_ambiguous_characters()
    {
        for ($i = 0; $i < 50; $i++) {
            $token = CbtController::generateCleanToken(6);
            $this->assertEquals(6, strlen($token));
            $this->assertEquals(strtoupper($token), $token);
            $this->assertFalse(str_contains($token, '0'));
            $this->assertFalse(str_contains($token, 'O'));
            $this->assertFalse(str_contains($token, '1'));
            $this->assertFalse(str_contains($token, 'I'));
        }
    }

    public function test_token_regeneration_endpoint_updates_quiz()
    {
        $quiz = Quiz::first();
        $this->assertNotNull($quiz);

        $teacherUser = User::where('role_id', 2)->first() ?: User::first();
        $request = Request::create("/api/quizzes/{$quiz->id}/token/regenerate", 'POST', [
            'expires_hours'   => 3,
            'max_violations'  => 4,
            'available_from'  => now()->subHour()->toDateTimeString(),
            'available_until' => now()->addHours(2)->toDateTimeString(),
        ]);
        $request->setUserResolver(fn() => $teacherUser);

        $response = app(CbtController::class)->regenerateToken($request, $quiz->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['data']['access_token']);
        $this->assertEquals(6, strlen($data['data']['access_token']));
        $this->assertEquals(4, $data['data']['max_violations']);

        $quiz->refresh();
        $this->assertEquals($data['data']['access_token'], $quiz->access_token);
        $this->assertEquals(4, $quiz->max_violations);
    }

    public function test_start_exam_rejects_invalid_token_and_throttles()
    {
        $student = Student::first();
        $quiz = Quiz::first();
        $user = $student->user;

        // Pasang token pada quiz
        $quiz->update([
            'access_token'     => 'ABC890',
            'token_expires_at' => now()->addHours(2),
            'available_from'   => now()->subHour(),
            'available_until'  => now()->addHours(2),
            'max_violations'   => 3,
        ]);

        // Bersihkan submission
        QuizSubmission::where('quiz_id', $quiz->id)->where('student_id', $student->id)->delete();
        $throttleKey = 'cbt-token:' . $student->id . ':' . $quiz->id;
        RateLimiter::clear($throttleKey);

        // 1. Coba tanpa token -> ditolak 422
        $reqWithoutToken = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST');
        $reqWithoutToken->setUserResolver(fn() => $user);
        $res1 = app(CbtController::class)->startExam($reqWithoutToken, $quiz->id);
        $this->assertEquals(422, $res1->getStatusCode());

        // 2. Coba token salah 4 kali lagi (total 5 percobaan gagal)
        for ($i = 0; $i < 4; $i++) {
            $reqWrong = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST', ['token' => 'SALAH1']);
            $reqWrong->setUserResolver(fn() => $user);
            $res = app(CbtController::class)->startExam($reqWrong, $quiz->id);
            $this->assertEquals(422, $res->getStatusCode());
        }

        // 3. Percobaan ke-6 harus kena throttle 429
        $reqThrottled = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST', ['token' => 'SALAH1']);
        $reqThrottled->setUserResolver(fn() => $user);
        $resThrottle = app(CbtController::class)->startExam($reqThrottled, $quiz->id);
        $this->assertEquals(429, $resThrottle->getStatusCode());

        // Reset throttle untuk tes berikutnya
        RateLimiter::clear($throttleKey);
    }

    public function test_start_exam_rejects_outside_available_window()
    {
        $student = Student::first();
        $quiz = Quiz::first();
        $user = $student->user;

        QuizSubmission::where('quiz_id', $quiz->id)->where('student_id', $student->id)->delete();

        // 1. Ujian masa depan
        $quiz->update([
            'access_token'    => null,
            'available_from'  => now()->addHours(3),
            'available_until' => now()->addHours(5),
        ]);
        $reqFuture = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST');
        $reqFuture->setUserResolver(fn() => $user);
        $resFuture = app(CbtController::class)->startExam($reqFuture, $quiz->id);
        $this->assertEquals(422, $resFuture->getStatusCode());
        $this->assertStringContainsString('belum dibuka', json_decode($resFuture->getContent(), true)['message']);

        // 2. Ujian masa lalu (kedaluwarsa)
        $quiz->update([
            'available_from'  => now()->subHours(5),
            'available_until' => now()->subHours(2),
        ]);
        $reqPast = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST');
        $reqPast->setUserResolver(fn() => $user);
        $resPast = app(CbtController::class)->startExam($reqPast, $quiz->id);
        $this->assertEquals(422, $resPast->getStatusCode());
        $this->assertStringContainsString('telah berakhir', json_decode($resPast->getContent(), true)['message']);
    }

    public function test_valid_token_allows_exam_and_subsequent_request_does_not_need_token()
    {
        $student = Student::first();
        $quiz = Quiz::first();
        $user = $student->user;

        $token = 'SEC999';
        $quiz->update([
            'access_token'     => $token,
            'token_expires_at' => now()->addHours(2),
            'available_from'   => now()->subHour(),
            'available_until'  => now()->addHours(2),
            'max_violations'   => 3,
            'duration_minutes' => 60,
        ]);

        QuizSubmission::where('quiz_id', $quiz->id)->where('student_id', $student->id)->delete();
        RateLimiter::clear('cbt-token:' . $student->id . ':' . $quiz->id);

        // 1. Mulai dengan token valid
        $reqValid = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST', ['token' => 'sec999']);
        $reqValid->setUserResolver(fn() => $user);
        $resValid = app(CbtController::class)->startExam($reqValid, $quiz->id);
        $this->assertEquals(200, $resValid->getStatusCode());
        $data = json_decode($resValid->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertGreaterThan(0, $data['data']['remaining_seconds']);

        // 2. Request kedua (reload halaman siswa) tanpa kirim token lagi harus langsung berhasil!
        $reqReload = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST');
        $reqReload->setUserResolver(fn() => $user);
        $resReload = app(CbtController::class)->startExam($reqReload, $quiz->id);
        $this->assertEquals(200, $resReload->getStatusCode());
        $dataReload = json_decode($resReload->getContent(), true);
        $this->assertTrue($dataReload['success']);
    }

    public function test_violations_increment_and_terminate_exam_when_reaching_limit()
    {
        $student = Student::first();
        $quiz = Quiz::first();
        $user = $student->user;

        $quiz->update([
            'access_token'     => null,
            'available_from'   => now()->subHour(),
            'available_until'  => now()->addHours(2),
            'max_violations'   => 3,
        ]);

        QuizSubmission::where('quiz_id', $quiz->id)->where('student_id', $student->id)->delete();

        // Mulai ujian
        $startReq = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST');
        $startReq->setUserResolver(fn() => $user);
        app(CbtController::class)->startExam($startReq, $quiz->id);

        // Pelanggaran 1
        $v1Req = Request::create("/api/quizzes/{$quiz->id}/cbt/violation", 'POST', ['violation_type' => 'blur']);
        $v1Req->setUserResolver(fn() => $user);
        $v1Res = app(CbtController::class)->recordViolation($v1Req, $quiz->id);
        $v1Data = json_decode($v1Res->getContent(), true);
        $this->assertFalse($v1Data['terminated']);
        $this->assertEquals(1, $v1Data['violation_count']);
        $this->assertEquals(2, $v1Data['remaining_violations']);

        // Pelanggaran 2
        $v2Req = Request::create("/api/quizzes/{$quiz->id}/cbt/violation", 'POST', ['violation_type' => 'visibilitychange']);
        $v2Req->setUserResolver(fn() => $user);
        $v2Res = app(CbtController::class)->recordViolation($v2Req, $quiz->id);
        $v2Data = json_decode($v2Res->getContent(), true);
        $this->assertFalse($v2Data['terminated']);
        $this->assertEquals(2, $v2Data['violation_count']);

        // Pelanggaran 3 (Mencapai batas max 3) -> Ujian harus dihentikan otomatis!
        $v3Req = Request::create("/api/quizzes/{$quiz->id}/cbt/violation", 'POST', ['violation_type' => 'fullscreen_exit']);
        $v3Req->setUserResolver(fn() => $user);
        $v3Res = app(CbtController::class)->recordViolation($v3Req, $quiz->id);
        $v3Data = json_decode($v3Res->getContent(), true);
        $this->assertTrue($v3Data['terminated']);
        $this->assertEquals('violation', $v3Data['finished_reason']);

        // Cek di database status submission
        $sub = QuizSubmission::where('quiz_id', $quiz->id)->where('student_id', $student->id)->first();
        $this->assertTrue(in_array($sub->status, ['submitted', 'graded']));
        $this->assertEquals('violation', $sub->finished_reason);
        $this->assertNotNull($sub->submitted_at);
        $this->assertEquals(3, $sub->violation_count);
    }
}
