<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $query = Quiz::with('meet');

        if ($request->filled('meet_id')) {
            $query->where('meet_id', $request->meet_id);
        }

        $quizzes = $query->withCount('submissions')->orderBy('id', 'desc')->get();

        return response()->json($quizzes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meet_id'          => 'required|exists:meets,id',
            'title'            => 'required|string|max:255',
            'instructions'     => 'nullable|string',
            'quiz_type'        => 'required|in:assignment,quiz,exam',
            'start_time'       => 'nullable|date',
            'due_date'         => 'required|date',
            'max_score'        => 'required|numeric|min:1|max:100',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        $quiz = Quiz::create($validated);
        $quiz->load('meet');

        return response()->json([
            'message' => 'Tugas / Kuis berhasil dibuat',
            'data'    => $quiz
        ], 201);
    }

    public function show($id)
    {
        $quiz = Quiz::with(['meet', 'submissions.student'])->findOrFail($id);
        return response()->json($quiz);
    }

    public function update(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        $validated = $request->validate([
            'meet_id'          => 'required|exists:meets,id',
            'title'            => 'required|string|max:255',
            'instructions'     => 'nullable|string',
            'quiz_type'        => 'required|in:assignment,quiz,exam',
            'start_time'       => 'nullable|date',
            'due_date'         => 'required|date',
            'max_score'        => 'required|numeric|min:1|max:100',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        $quiz->update($validated);
        $quiz->load('meet');

        return response()->json([
            'message' => 'Tugas / Kuis berhasil diperbarui',
            'data'    => $quiz
        ]);
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
