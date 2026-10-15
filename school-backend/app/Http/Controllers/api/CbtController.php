<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\QuestionBank;
use App\Services\QuestionScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

class CbtController extends Controller
{
    /**
     * Menghasilkan token acak 6 karakter ramah baca tanpa karakter mirip (0/O, 1/I).
     */
    public static function generateCleanToken(int $length = 6): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $token = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[random_int(0, $max)];
        }
        return $token;
    }

    /**
     * Import butir soal dari Bank Soal ke dalam Kuis
     */
    public function importBankItems(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);

        $validated = $request->validate([
            'question_bank_id' => 'required|exists:question_banks,id',
            'item_ids'         => 'nullable|array',
            'item_ids.*'       => 'exists:question_bank_items,id',
        ]);

        $bank = QuestionBank::with('items')->findOrFail($validated['question_bank_id']);

        $itemsQuery = $bank->items();
        if (!empty($validated['item_ids'])) {
            $itemsQuery->whereIn('id', $validated['item_ids']);
        }
        $selectedItems = $itemsQuery->get();

        if ($selectedItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada butir soal yang dapat diimpor.'
            ], 422);
        }

        // Format snapshot butir soal
        $questionsSnapshot = [];
        $totalWeight = 0;

        foreach ($selectedItems as $index => $item) {
            $questionsSnapshot[] = [
                'number'         => $index + 1,
                'id'             => $item->id,
                'type'           => $item->type,
                'question'       => $item->question,
                'options'        => $item->options,
                'data'           => $item->data,
                'correct_answer' => $item->correct_answer,
                'difficulty'     => $item->difficulty,
                'weight'         => (float) $item->weight,
                'explanation'    => $item->explanation,
            ];
            $totalWeight += (float) $item->weight;
        }

        $quiz->update([
            'question_bank_id' => $bank->id,
            'questions_data'   => $questionsSnapshot,
            'quiz_type'        => 'multiple_choice',
            'max_score'        => $totalWeight > 0 ? $totalWeight : 100,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengimpor ' . count($questionsSnapshot) . ' butir soal ke dalam ujian',
            'data'    => [
                'quiz'            => $quiz,
                'total_questions' => count($questionsSnapshot),
                'max_score'       => $quiz->max_score,
            ]
        ]);
    }

    /**
     * Guru: Regenerasi Token Ujian Baru (Iterasi 2: B2)
     */
    public function regenerateToken(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);

        $validated = $request->validate([
            'custom_token'     => 'nullable|string|min:4|max:10',
            'expires_hours'    => 'nullable|numeric|min:0.5|max:48',
            'available_from'   => 'nullable|date',
            'available_until'  => 'nullable|date',
            'max_violations'   => 'nullable|integer|min:1|max:20',
        ]);

        $token = !empty($validated['custom_token'])
            ? strtoupper(trim($validated['custom_token']))
            : self::generateCleanToken(6);

        $expiresHours = $validated['expires_hours'] ?? 4;
        $expiresAt = now()->addMinutes((int) ($expiresHours * 60));

        $updateData = [
            'access_token'     => $token,
            'token_expires_at' => $expiresAt,
        ];

        if (array_key_exists('available_from', $validated)) {
            $updateData['available_from'] = $validated['available_from'];
        }
        if (array_key_exists('available_until', $validated)) {
            $updateData['available_until'] = $validated['available_until'];
        }
        if (array_key_exists('max_violations', $validated)) {
            $updateData['max_violations'] = $validated['max_violations'];
        }

        $quiz->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Token ujian berhasil dibuat dan diperbarui.',
            'data'    => [
                'quiz_id'          => $quiz->id,
                'access_token'     => $quiz->access_token,
                'token_expires_at' => $quiz->token_expires_at,
                'available_from'   => $quiz->available_from,
                'available_until'  => $quiz->available_until,
                'max_violations'   => $quiz->max_violations ?: 3,
            ]
        ]);
    }

    /**
     * Guru: Perbarui Pengaturan Keamanan Ujian (Token, Jendela Waktu, Pelanggaran Layar)
     */
    public function updateSecuritySettings(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);

        $validated = $request->validate([
            'access_token'     => 'nullable|string|max:10',
            'token_expires_at' => 'nullable|date',
            'available_from'   => 'nullable|date',
            'available_until'  => 'nullable|date|after_or_equal:available_from',
            'max_violations'   => 'nullable|integer|min:1|max:20',
            'duration_minutes' => 'nullable|integer|min:5|max:300',
        ]);

        if (isset($validated['access_token'])) {
            $validated['access_token'] = strtoupper(trim($validated['access_token']));
        }

        $quiz->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan keamanan dan jadwal ujian berhasil disimpan.',
            'data'    => $quiz,
        ]);
    }

    /**
     * Siswa: Dapatkan Info Persiapan Ujian Sebelum Mulai (F2 Siswa)
     */
    public function getExamInfo(Request $request, $quizId)
    {
        $quiz = Quiz::with('meet.course')->findOrFail($quizId);
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $submission = QuizSubmission::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->first();

        $hasActiveSession = $submission && $submission->started_at && !$submission->submitted_at;
        $isSubmitted = $submission && (bool) $submission->submitted_at;

        return response()->json([
            'success' => true,
            'data'    => [
                'quiz_id'            => $quiz->id,
                'quiz_title'         => $quiz->title,
                'instructions'       => $quiz->instructions,
                'duration_minutes'   => $quiz->duration_minutes ?: 60,
                'total_questions'    => is_array($quiz->questions_data) ? count($quiz->questions_data) : 0,
                'requires_token'     => !empty($quiz->access_token) && !$hasActiveSession,
                'available_from'     => $quiz->available_from,
                'available_until'    => $quiz->available_until,
                'max_violations'     => $quiz->max_violations ?: 3,
                'has_active_session' => $hasActiveSession,
                'is_submitted'       => $isSubmitted,
                'score'              => $submission?->score,
                'status'             => $submission?->status,
                'finished_reason'    => $submission?->finished_reason,
            ]
        ]);
    }

    /**
     * Memulai Ujian CBT (Sisi Siswa) dengan Validasi Token, Jendela Waktu, dan Throttle (B3, B4, B5, B6)
     */
    public function startExam(Request $request, $quizId)
    {
        $quiz = Quiz::with('meet.course')->findOrFail($quizId);
        $student = $request->user()->student;

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya siswa yang berhak mengakses sesi ujian ini.'
            ], 403);
        }

        // 1. Validasi Jendela Waktu (B5)
        $now = now();
        if ($quiz->available_from && $now->isBefore(Carbon::parse($quiz->available_from))) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi ujian belum dibuka. Ujian baru dapat diakses pada ' . Carbon::parse($quiz->available_from)->translatedFormat('d F Y H:i') . ' WIB.'
            ], 422);
        }

        if ($quiz->available_until && $now->isAfter(Carbon::parse($quiz->available_until))) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi ujian telah berakhir sejak ' . Carbon::parse($quiz->available_until)->translatedFormat('d F Y H:i') . ' WIB.'
            ], 422);
        }

        // 2. Ambil atau buat submission
        $submission = QuizSubmission::firstOrCreate(
            [
                'quiz_id'    => $quiz->id,
                'student_id' => $student->id,
            ],
            [
                'started_at'      => null, // Belum mulai sampai validasi token lolos
                'status'          => 'in_progress',
                'answers'         => [],
                'violation_count' => 0,
            ]
        );

        // Jika sudah dikumpulkan sebelumnya, kembalikan hasil
        if ($submission->submitted_at) {
            return response()->json([
                'success' => true,
                'data' => [
                    'quiz_title'        => $quiz->title,
                    'duration_minutes'  => $quiz->duration_minutes ?: 60,
                    'remaining_seconds' => 0,
                    'started_at'        => $submission->started_at,
                    'status'            => 'submitted',
                    'is_graded'         => (bool) $submission->is_graded,
                    'score'             => $submission->score,
                    'finished_reason'   => $submission->finished_reason,
                    'questions'         => [],
                ]
            ]);
        }

        // 3. Validasi Token Ujian (B3, B4)
        $hasActiveSession = $submission->started_at !== null;

        // Siswa yang baru mulai wajib memasukkan token yang valid jika token diaktifkan
        if (!$hasActiveSession && !empty($quiz->access_token)) {
            $throttleKey = 'cbt-token:' . $student->id . ':' . $quiz->id;

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                return response()->json([
                    'success'     => false,
                    'message'     => "Terlalu banyak percobaan token yang salah. Silakan coba lagi dalam {$seconds} detik.",
                    'retry_after' => $seconds,
                ], 429);
            }

            $inputToken = strtoupper(trim((string) $request->input('token', '')));

            if ($inputToken === '' || $inputToken !== strtoupper($quiz->access_token)) {
                RateLimiter::hit($throttleKey, 60);
                return response()->json([
                    'success' => false,
                    'message' => 'Token ujian tidak valid. Pastikan Anda memasukkan token yang diberikan oleh guru pengawas.'
                ], 422);
            }

            if ($quiz->token_expires_at && $now->isAfter(Carbon::parse($quiz->token_expires_at))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token ujian telah kedaluwarsa. Silakan minta guru untuk memperbarui token.'
                ], 422);
            }

            // Bersihkan limiter percobaan token jika berhasil
            RateLimiter::clear($throttleKey);
        }

        // Mulai sesi jika belum
        if (!$submission->started_at) {
            $submission->update([
                'started_at'      => now(),
                'status'          => 'in_progress',
                'violation_count' => 0,
            ]);
        }

        // 4. Hitung Sisa Waktu di Server (B6)
        $durationMinutes = $quiz->duration_minutes ?: 60;
        $startTime = Carbon::parse($submission->started_at);
        $endTimeByDuration = (clone $startTime)->addMinutes($durationMinutes);

        // Batas akhir tidak boleh melebihi available_until
        $effectiveEndTime = $quiz->available_until
            ? (clone $endTimeByDuration)->min(Carbon::parse($quiz->available_until))
            : $endTimeByDuration;

        $remainingSeconds = (int) now()->diffInSeconds($effectiveEndTime, false);

        // Jika waktu di server telah habis, selesaikan otomatis dengan alasan 'timeout'
        if ($remainingSeconds <= 0) {
            $finalized = $this->gradeAndFinalizeSubmission(
                $submission,
                $quiz,
                $submission->answers ?: [],
                'timeout'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'quiz_title'        => $quiz->title,
                    'duration_minutes'  => $durationMinutes,
                    'remaining_seconds' => 0,
                    'started_at'        => $submission->started_at,
                    'status'            => 'submitted',
                    'is_graded'         => (bool) $submission->is_graded,
                    'score'             => $submission->score,
                    'finished_reason'   => 'timeout',
                    'questions'         => [],
                    'breakdown'         => $finalized['breakdown'],
                ]
            ]);
        }

        // 5. Format Soal (Sanitasi Kunci & Pembahasan)
        $questions = [];
        $rawQuestions = $quiz->questions_data ?: [];
        $seed = $submission->id . '-' . $student->id;

        foreach ($rawQuestions as $q) {
            $questions[] = QuestionScorer::sanitize($q, $seed);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'quiz_title'         => $quiz->title,
                'duration_minutes'   => $durationMinutes,
                'remaining_seconds'  => max(0, $remainingSeconds),
                'started_at'         => $submission->started_at,
                'saved_answers'      => $submission->answers ?: new \stdClass(),
                'status'             => $submission->submitted_at ? $submission->status : 'in_progress',
                'is_graded'          => (bool) $submission->is_graded,
                'score'              => $submission->score,
                'max_violations'     => $quiz->max_violations ?: 3,
                'violation_count'    => $submission->violation_count ?: 0,
                'finished_reason'    => $submission->finished_reason,
                'questions'          => $questions,
            ]
        ]);
    }

    /**
     * Autosave Jawaban Siswa Berkala
     */
    public function saveProgress(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        $submission = QuizSubmission::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        // Jangan izinkan update jika sudah disubmit
        if ($submission->submitted_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ujian telah selesai, jawaban tidak dapat diubah.',
            ], 422);
        }

        $submission->update([
            'answers' => $validated['answers'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jawaban berhasil disimpan otomatis',
            'data'    => [
                'saved_at' => now(),
            ]
        ]);
    }

    /**
     * Catat Pelanggaran Layar Fokus (Blur / Pindah Tab / Keluar Fullscreen) - (B7)
     */
    public function recordViolation(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $submission = QuizSubmission::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        if ($submission->submitted_at) {
            return response()->json([
                'success' => true,
                'terminated' => true,
                'message' => 'Ujian sudah diselesaikan.',
            ]);
        }

        $maxViolations = $quiz->max_violations ?: 3;
        $submission->increment('violation_count');
        $currentCount = $submission->violation_count;

        // Jika pelanggaran melebihi atau sama dengan batas maksimal, kumpulkan paksa!
        if ($currentCount >= $maxViolations) {
            $answers = $submission->answers ?: [];
            $finalized = $this->gradeAndFinalizeSubmission($submission, $quiz, $answers, 'violation');

            return response()->json([
                'success'          => true,
                'terminated'       => true,
                'violation_count'  => $currentCount,
                'max_violations'   => $maxViolations,
                'finished_reason'  => 'violation',
                'message'          => "Ujian dihentikan otomatis karena Anda telah meninggalkan layar ujian sebanyak {$maxViolations} kali.",
                'data'             => [
                    'score'        => $submission->score,
                    'score_pg'     => $submission->score_pg,
                    'is_graded'    => $submission->is_graded,
                    'breakdown'    => $finalized['breakdown'],
                ]
            ]);
        }

        return response()->json([
            'success'              => true,
            'terminated'           => false,
            'violation_count'      => $currentCount,
            'max_violations'       => $maxViolations,
            'remaining_violations' => max(0, $maxViolations - $currentCount),
            'message'              => "Peringatan fokus ujian! Anda terdeteksi meninggalkan layar ujian ({$currentCount}/{$maxViolations}).",
        ]);
    }

    /**
     * Selesaikan Ujian & Hitung Nilai Otomatis (Manual Siswa)
     */
    public function finishExam(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        $student = $request->user()->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'answers' => 'nullable|array',
        ]);

        $submission = QuizSubmission::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $answers = $validated['answers'] ?? $submission->answers ?? [];
        $finalized = $this->gradeAndFinalizeSubmission($submission, $quiz, $answers, 'manual');

        return response()->json([
            'success' => true,
            'message' => 'Ujian berhasil diselesaikan dan dikumpulkan.',
            'data'    => [
                'score'           => $submission->score,
                'score_pg'        => $submission->score_pg,
                'is_graded'       => $submission->is_graded,
                'has_essay'       => $finalized['has_essay'],
                'finished_reason' => 'manual',
                'submitted_at'    => $submission->submitted_at,
                'breakdown'       => $finalized['breakdown'],
            ]
        ]);
    }

    /**
     * Helper Penskoran dan Finalisasi Submission Terpusat
     */
    private function gradeAndFinalizeSubmission(QuizSubmission $submission, Quiz $quiz, array $answers, string $reason = 'manual'): array
    {
        $rawQuestions = $quiz->questions_data ?: [];

        $totalPgScore = 0.0;
        $maxPgScore = 0.0;
        $hasEssay = false;
        $breakdown = [];

        foreach ($rawQuestions as $q) {
            $num = (string) $q['number'];
            $result = QuestionScorer::score($q, $answers[$num] ?? null);

            if ($result['manual']) {
                $hasEssay = true;
            } else {
                $maxPgScore += $result['max'];
                $totalPgScore += $result['earned'];
            }

            $breakdown[] = [
                'number' => $q['number'],
                'type'   => $q['type'] ?? 'pg',
                'status' => $result['status'],
                'earned' => $result['earned'],
                'max'    => $result['max'],
            ];
        }

        // Hitung skala 100 jika maxPgScore ada
        $finalPgScore = $maxPgScore > 0 ? round(($totalPgScore / $maxPgScore) * 100, 2) : $totalPgScore;

        $updateData = [
            'answers'         => $answers,
            'submitted_at'    => now(),
            'score_pg'        => $finalPgScore,
            'status'          => 'submitted',
            'finished_reason' => $reason,
        ];

        // Jika tidak ada soal esai, finalisasi otomatis
        if (!$hasEssay) {
            $updateData['score'] = $finalPgScore;
            $updateData['is_graded'] = true;
            $updateData['status'] = 'graded';
        }

        $submission->update($updateData);

        return [
            'final_score' => $submission->score,
            'score_pg'    => $finalPgScore,
            'has_essay'   => $hasEssay,
            'breakdown'   => $breakdown,
        ];
    }

    /**
     * Penilaian Jawaban Esai oleh Guru
     */
    public function gradeCbtSubmission(Request $request, $submissionId)
    {
        $submission = QuizSubmission::with(['quiz', 'student'])->findOrFail($submissionId);

        $validated = $request->validate([
            'score_essay'      => 'required|numeric|min:0|max:100',
            'teacher_feedback' => 'nullable|string',
        ]);

        $scorePg = (float) ($submission->score_pg ?? 0);
        $scoreEssay = (float) $validated['score_essay'];
        
        $totalScore = round(($scorePg + $scoreEssay) / 2, 2);

        $submission->update([
            'score_essay'      => $scoreEssay,
            'score'            => $totalScore,
            'teacher_feedback' => $validated['teacher_feedback'],
            'is_graded'        => true,
            'status'           => 'graded',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nilai esai dan umpan balik berhasil disimpan',
            'data'    => $submission
        ]);
    }
}
