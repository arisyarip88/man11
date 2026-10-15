<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\CbtController;
use App\Models\Quiz;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CbtExamFlowTest extends TestCase
{
    public function test_start_exam_does_not_leak_correct_keys()
    {
        $student = Student::where('nis', '2026000002')->first();
        $this->assertNotNull($student, 'Student Fadhil must exist from seeder');

        $quiz = Quiz::where('title', 'like', '%5 Tipe Soal%')->first();
        $this->assertNotNull($quiz, 'Quiz with 5 types must exist from seeder');

        $user = $student->user;
        $request = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST');
        $request->setUserResolver(fn() => $user);

        $controller = app(CbtController::class);
        $response = $controller->startExam($request, $quiz->id);
        $result = json_decode($response->getContent(), true);

        $this->assertTrue($result['success']);
        $questions = $result['data']['questions'];
        $this->assertCount(10, $questions);

        foreach ($questions as $q) {
            // Options tidak boleh ada is_correct
            if (!empty($q['options'])) {
                foreach ($q['options'] as $opt) {
                    $this->assertArrayNotHasKey('is_correct', $opt, "Key is_correct leaked in question {$q['number']}");
                }
            }
            // Tidak boleh ada accepted_answers
            $this->assertArrayNotHasKey('accepted_answers', $q['data'] ?? []);
            // Menjodohkan tidak boleh membocorkan pasangan kanan di pairs
            if ($q['type'] === 'menjodohkan') {
                $this->assertArrayHasKey('pairs', $q);
                $this->assertArrayHasKey('choices', $q);
                foreach ($q['pairs'] as $p) {
                    $this->assertArrayNotHasKey('right', $p, "Pairs leaked right value in question {$q['number']}");
                }
            }
            // Benar/Salah tidak boleh ada 'answer'
            if ($q['type'] === 'benar_salah') {
                $this->assertArrayHasKey('statements', $q);
                foreach ($q['statements'] as $st) {
                    $this->assertArrayNotHasKey('answer', $st, "Statement leaked answer in question {$q['number']}");
                }
            }
            // Tidak boleh ada correct_answer atau explanation
            $this->assertArrayNotHasKey('correct_answer', $q);
            $this->assertArrayNotHasKey('explanation', $q);
        }
    }

    public function test_finish_exam_auto_grades_all_five_types()
    {
        $student = Student::where('nis', '2026000002')->first();
        $quiz = Quiz::where('title', 'like', '%5 Tipe Soal%')->first();
        $user = $student->user;

        // Simulasi jawaban siswa (9 butir benar, 1 butir salah di nomor 2)
        $studentAnswers = [
            '1' => 'A',                             // PG: Benar (10 poin)
            '2' => 'B',                             // PG: Salah (kunci A, dapat 0 poin)
            '3' => ['A', 'B', 'D'],                 // PG Kompleks: Benar semua (10 poin)
            '4' => ['A', 'B', 'D'],                 // PG Kompleks: Benar semua (10 poin)
            '5' => 'Foto Sintesis',                 // Isian: Benar (10 poin - case insensitive)
            '6' => 'Ampere',                        // Isian: Benar (10 poin)
            '7' => [                                // Menjodohkan: Benar semua (10 poin)
                '1' => 'Tempat fotosintesis',
                '2' => 'Sintesis protein',
                '3' => 'Pengendali aktivitas dan materi genetik sel',
            ],
            '8' => [                                // Menjodohkan: Benar semua (10 poin)
                '1' => 'Radiasi foton cahaya matahari',
                '2' => 'Hembusan kinetik angin',
                '3' => 'Panas uap bumi (geotermal)',
            ],
            '9' => ['1' => true, '2' => false, '3' => true], // Benar salah: Benar semua (10 poin)
            '10' => ['1' => true, '2' => false],             // Benar salah: Benar semua (10 poin)
        ];

        // Mulai ujian dulu
        $startReq = Request::create("/api/quizzes/{$quiz->id}/cbt/start", 'POST');
        $startReq->setUserResolver(fn() => $user);
        app(CbtController::class)->startExam($startReq, $quiz->id);

        // Kumpulkan ujian
        $finishReq = Request::create("/api/quizzes/{$quiz->id}/cbt/finish", 'POST', ['answers' => $studentAnswers]);
        $finishReq->setUserResolver(fn() => $user);
        $response = app(CbtController::class)->finishExam($finishReq, $quiz->id);

        $result = json_decode($response->getContent(), true);
        $this->assertTrue($result['success']);
        
        // 90 dari 100 poin (karena nomor 2 salah)
        $this->assertEquals(90.00, $result['data']['score']);
        $this->assertTrue($result['data']['is_graded']);
        $this->assertFalse($result['data']['has_essay']);
        $this->assertCount(10, $result['data']['breakdown']);
    }
}
