<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CbtController extends Controller
{
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
     * Memulai Ujian CBT (Sisi Siswa)
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

        // Ambil atau buat submission
        $submission = QuizSubmission::firstOrCreate(
            [
                'quiz_id'    => $quiz->id,
                'student_id' => $student->id,
            ],
            [
                'started_at' => now(),
                'status'     => 'submitted',
                'answers'    => [],
            ]
        );

        if (!$submission->started_at) {
            $submission->update(['started_at' => now()]);
        }

        // Hitung sisa waktu jika memiliki durasi
        $durationMinutes = $quiz->duration_minutes ?: 60;
        $startTime = $submission->started_at;
        $endTime = (clone $startTime)->addMinutes($durationMinutes);
        $now = now();
        $remainingSeconds = max(0, $endTime->diffInSeconds($now, false));

        // Format soal untuk siswa (SEMBUNYIKAN KUNCI JAWABAN & PEMBAHASAN)
        $questions = [];
        $rawQuestions = $quiz->questions_data ?: [];

        foreach ($rawQuestions as $q) {
            $sanitizedOptions = [];
            if (!empty($q['options']) && is_array($q['options'])) {
                foreach ($q['options'] as $opt) {
                    $sanitizedOptions[] = [
                        'key'  => $opt['key'],
                        'text' => $opt['text'],
                    ];
                }
            }

            $questions[] = [
                'number'     => $q['number'],
                'type'       => $q['type'],
                'question'   => $q['question'],
                'options'    => $sanitizedOptions,
                'weight'     => $q['weight'] ?? 1,
                'difficulty' => $q['difficulty'] ?? 'medium',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'quiz_title'         => $quiz->title,
                'duration_minutes'   => $durationMinutes,
                'remaining_seconds'  => $remainingSeconds,
                'started_at'         => $submission->started_at,
                'saved_answers'      => $submission->answers ?: new \stdClass(),
                'status'             => $submission->status,
                'is_graded'          => $submission->is_graded,
                'score'              => $submission->score,
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
     * Selesaikan Ujian & Hitung Nilai PG Otomatis
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
        $rawQuestions = $quiz->questions_data ?: [];

        $totalPgScore = 0;
        $maxPgScore = 0;
        $hasEssay = false;

        foreach ($rawQuestions as $q) {
            $num = (string) $q['number'];
            $studentAnswer = $answers[$num] ?? null;
            $weight = (float) ($q['weight'] ?? 1.0);

            if ($q['type'] === 'pg') {
                $maxPgScore += $weight;
                $correctKey = null;
                if (!empty($q['options']) && is_array($q['options'])) {
                    foreach ($q['options'] as $opt) {
                        if (!empty($opt['is_correct'])) {
                            $correctKey = $opt['key'];
                            break;
                        }
                    }
                }

                if ($correctKey && strtoupper((string)$studentAnswer) === strtoupper((string)$correctKey)) {
                    $totalPgScore += $weight;
                }
            } elseif ($q['type'] === 'esai' || $q['type'] === 'isian') {
                $hasEssay = true;
            }
        }

        // Hitung skala 100 jika maxPgScore ada
        $finalPgScore = $maxPgScore > 0 ? round(($totalPgScore / $maxPgScore) * 100, 2) : $totalPgScore;

        $updateData = [
            'answers'      => $answers,
            'submitted_at' => now(),
            'score_pg'     => $finalPgScore,
            'status'       => 'submitted',
        ];

        // Jika tidak ada soal esai, langsung finalisasi nilai
        if (!$hasEssay) {
            $updateData['score'] = $finalPgScore;
            $updateData['is_graded'] = true;
            $updateData['status'] = 'graded';
        }

        $submission->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Ujian berhasil diselesaikan dan dikumpulkan.',
            'data'    => [
                'score'        => $submission->score,
                'score_pg'     => $finalPgScore,
                'is_graded'    => $submission->is_graded,
                'has_essay'    => $hasEssay,
                'submitted_at' => $submission->submitted_at,
            ]
        ]);
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
        
        // Asumsi proporsi bobot: jika ada PG dan Esai, rata-rata atau gabungan sesuai skala
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
