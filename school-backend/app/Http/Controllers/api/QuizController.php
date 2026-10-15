<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $query = Quiz::with(['meet.course.subject', 'meet.course.class.className', 'submissions.student']);

        $user = $request->user();
        if ($request->filled('my_quizzes') && $user) {
            if ($user->teacher) {
                $teacherId = $user->teacher->id;
                $query->whereHas('meet.course', function ($cq) use ($teacherId) {
                    $cq->where('teacher_id', $teacherId);
                });
            } elseif ($user->student) {
                $classIds = $user->student->classes()->pluck('classes.id')->toArray();
                $query->whereHas('meet.course', function ($cq) use ($classIds) {
                    $cq->whereIn('class_id', $classIds);
                });
            }
        }

        if ($request->filled('meet_id')) {
            $query->where('meet_id', $request->meet_id);
        }

        if ($request->filled('quiz_type')) {
            $query->where('quiz_type', $request->quiz_type);
        }

        $quizzes = $query->withCount('submissions')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $quizzes
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meet_id'           => 'required|exists:meets,id',
            'title'             => 'required|string|max:255',
            'instructions'      => 'nullable|string',
            'quiz_type'         => 'required|in:assignment,quiz,exam,multiple_choice',
            'activity_category' => 'nullable|in:pretest,quiz,assignment,posttest',
            'question_bank_id'  => 'nullable|exists:question_banks,id',
            'start_time'        => 'nullable|date',
            'due_date'          => 'nullable|date',
            'available_from'    => 'nullable|date',
            'available_until'   => 'nullable|date',
            'max_score'         => 'nullable|numeric|min:1|max:100',
            'duration_minutes'  => 'nullable|integer|min:1',
            'questions_data'    => 'nullable|array',
        ]);

        if (empty($validated['due_date'])) {
            $validated['due_date'] = !empty($validated['available_until']) 
                ? $validated['available_until'] 
                : now()->addDays(7);
        }

        if (empty($validated['max_score'])) {
            $validated['max_score'] = 100;
        }

        if (empty($validated['activity_category'])) {
            $validated['activity_category'] = match ($validated['quiz_type']) {
                'assignment'      => 'assignment',
                'exam'            => 'quiz',
                'quiz', 'multiple_choice' => 'quiz',
                default           => 'quiz',
            };
        }

        // Format manual questions if provided
        if (!empty($validated['questions_data'])) {
            [$questions, $totalWeight] = $this->formatQuestionsData($validated['questions_data']);
            $validated['questions_data'] = $questions;
            if ($totalWeight > 0) {
                $validated['max_score'] = min(100, $totalWeight);
            }
        }

        $quiz = Quiz::create($validated);

        // If question_bank_id provided and no manual questions, auto-import from bank
        if (!empty($validated['question_bank_id']) && empty($validated['questions_data'])) {
            $bank = \App\Models\QuestionBank::with('items')->find($validated['question_bank_id']);
            if ($bank && $bank->items->isNotEmpty()) {
                $questionsSnapshot = [];
                $totalWeight = 0;
                foreach ($bank->items as $index => $item) {
                    $w = (float) ($item->weight ?: 10);
                    $totalWeight += $w;
                    $questionsSnapshot[] = [
                        'number'         => $index + 1,
                        'id'             => $item->id,
                        'type'           => $item->type,
                        'question'       => $item->question,
                        'options'        => $item->options,
                        'data'           => $item->data,
                        'correct_answer' => $item->correct_answer,
                        'difficulty'     => $item->difficulty,
                        'weight'         => $w,
                        'explanation'    => $item->explanation,
                    ];
                }
                $quiz->update([
                    'questions_data' => $questionsSnapshot,
                    'max_score'      => $totalWeight > 0 ? min(100, $totalWeight) : 100,
                ]);
            }
        }

        $quiz->load('meet');

        return response()->json([
            'success' => true,
            'message' => 'Tugas / Kuis berhasil dibuat',
            'data'    => $quiz
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $studentId = null;
        if ($user && in_array(strtolower($user->role?->name ?? ''), ['student', 'siswa'])) {
            $student = \App\Models\Student::where('nis', $user->username)->orWhere('email', $user->email)->first();
            $studentId = $student?->id;
        }

        $quiz = Quiz::with([
            'meet.course.subject',
            'meet.course.class.className',
            'meet.course.teacher',
            'meet.materials',
            'meet.quizzes',
            'meet.discussions',
            'questionBank',
            'submissions' => function ($sq) use ($studentId) {
                if ($studentId) {
                    $sq->where('student_id', $studentId);
                }
                $sq->with('student');
            },
        ])->findOrFail($id);

        return response()->json($quiz);
    }

    public function update(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        $validated = $request->validate([
            'meet_id'           => 'required|exists:meets,id',
            'title'             => 'required|string|max:255',
            'instructions'      => 'nullable|string',
            'quiz_type'         => 'required|in:assignment,quiz,exam,multiple_choice',
            'activity_category' => 'nullable|in:pretest,quiz,assignment,posttest',
            'question_bank_id'  => 'nullable|exists:question_banks,id',
            'start_time'        => 'nullable|date',
            'due_date'          => 'nullable|date',
            'available_from'    => 'nullable|date',
            'available_until'   => 'nullable|date',
            'max_score'         => 'nullable|numeric|min:1|max:100',
            'duration_minutes'  => 'nullable|integer|min:1',
            'questions_data'    => 'nullable|array',
        ]);

        if (array_key_exists('questions_data', $validated) && is_array($validated['questions_data'])) {
            [$questions, $totalWeight] = $this->formatQuestionsData($validated['questions_data']);
            $validated['questions_data'] = $questions;
            if ($totalWeight > 0) {
                $validated['max_score'] = min(100, $totalWeight);
            }
        }

        $quiz->update($validated);
        $quiz->load('meet');

        return response()->json([
            'success' => true,
            'message' => 'Tugas / Kuis berhasil diperbarui',
            'data'    => $quiz
        ]);
    }

    public function updateQuestions(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        $validated = $request->validate([
            'questions' => 'required|array',
        ]);

        [$questions, $totalWeight] = $this->formatQuestionsData($validated['questions']);

        $quiz->update([
            'questions_data' => $questions,
            'quiz_type'      => 'multiple_choice',
            'max_score'      => $totalWeight > 0 ? min(100, $totalWeight) : 100,
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'Butir soal kuis berhasil disimpan (' . count($questions) . ' butir)',
            'data'            => $quiz,
            'total_questions' => count($questions),
        ]);
    }

    /**
     * Standardize question formatting across all quiz operations.
     */
    private function formatQuestionsData(array $rawQuestions): array
    {
        $questions = [];
        $totalWeight = 0;
        foreach ($rawQuestions as $idx => $q) {
            $weight = isset($q['weight']) && is_numeric($q['weight']) ? (float) $q['weight'] : 10;
            $totalWeight += $weight;
            $type = $q['type'] ?? 'pg';

            $formatted = [
                'number'         => $idx + 1,
                'id'             => $q['id'] ?? null,
                'type'           => $type,
                'question'       => $q['question'] ?? '',
                'options'        => $q['options'] ?? [],
                'data'           => $q['data'] ?? null,
                'correct_answer' => $q['correct_answer'] ?? null,
                'difficulty'     => $q['difficulty'] ?? 'medium',
                'weight'         => $weight,
                'explanation'    => $q['explanation'] ?? '',
            ];

            // Default fallback for single-choice PG correct_answer if not provided
            if ($type === 'pg' && empty($formatted['correct_answer'])) {
                // Check if any option is flagged as correct
                $foundCorrect = false;
                foreach ($formatted['options'] as $opt) {
                    if (!empty($opt['is_correct'])) {
                        $formatted['correct_answer'] = $opt['key'] ?? 'A';
                        $foundCorrect = true;
                        break;
                    }
                }
                if (!$foundCorrect) {
                    $formatted['correct_answer'] = 'A';
                }
            }

            $questions[] = $formatted;
        }

        return [$questions, $totalWeight];
    }

    public function destroy($id)
    {
        $quiz = Quiz::findOrFail($id);
        $quiz->delete();

        return response()->json([
            'message' => 'Tugas / Kuis berhasil dihapus'
        ]);
    }
}
