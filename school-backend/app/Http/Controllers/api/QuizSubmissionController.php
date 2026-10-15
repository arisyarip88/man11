<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizSubmission;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuizSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $query = QuizSubmission::with(['quiz', 'student']);

        // Siswa hanya boleh melihat status pengumpulan miliknya sendiri.
        $role = strtolower($request->user()?->role?->name ?? '');
        if (($role === 'student' || $request->boolean('my_submissions')) && $request->user()) {
            $student = Student::where('nis', $request->user()->username)->orWhere('email', $request->user()->email)->first();
            if ($student) {
                $query->where('student_id', $student->id);
            } else {
                return response()->json([]);
            }
        }

        if ($request->filled('quiz_id')) {
            $query->where('quiz_id', $request->quiz_id);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function submit(Request $request)
    {
        $user = $request->user();
        $student = null;
        if ($user) {
            $student = Student::where('nis', $user->username)
                ->orWhere('email', $user->email)
                ->first();
        }

        if (!$student && $request->filled('student_id')) {
            $student = Student::find($request->student_id);
        }

        if (!$student) {
            $student = Student::first();
        }

        if (!$student) {
            return response()->json([
                'message' => 'Hanya siswa terdaftar yang dapat mengumpulkan tugas'
            ], 403);
        }

        $studentId = $student->id;

        $validated = $request->validate([
            'quiz_id'         => 'required|exists:quizzes,id',
            'submission_text' => 'nullable|string',
            'notes'           => 'nullable|string',
            'file'            => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('submissions', 'public');
        }

        $submissionText = $validated['submission_text'] ?? $validated['notes'] ?? null;

        $updatePayload = [
            'submitted_at' => Carbon::now(),
            'status'       => 'submitted',
        ];

        if ($submissionText !== null) {
            $updatePayload['submission_text'] = $submissionText;
        }

        if ($filePath !== null) {
            $updatePayload['file_path'] = $filePath;
        }

        $submission = QuizSubmission::updateOrCreate(
            [
                'quiz_id'    => $validated['quiz_id'],
                'student_id' => $studentId,
            ],
            $updatePayload
        );

        return response()->json([
            'message' => 'Tugas mandiri berhasil dikumpulkan!',
            'data'    => $submission->load(['quiz', 'student'])
        ], 201);
    }

    public function grade(Request $request, $id)
    {
        $submission = QuizSubmission::findOrFail($id);

        $validated = $request->validate([
            'score'            => 'required|numeric|min:0|max:100',
            'teacher_feedback' => 'nullable|string',
        ]);

        $submission->update([
            'score'            => $validated['score'],
            'score_essay'      => $validated['score'],
            'is_graded'        => true,
            'teacher_feedback' => $validated['teacher_feedback'] ?? null,
            'status'           => 'graded',
        ]);

        return response()->json([
            'message' => 'Nilai dan ulasan tugas berhasil disimpan',
            'data'    => $submission->load(['quiz', 'student'])
        ]);
    }

    public function mySubmission(Request $request, $quizId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $student = Student::where('nis', $user->username)->orWhere('email', $user->email)->first();
        if (!$student) {
            return response()->json(['data' => null]);
        }

        $submission = QuizSubmission::with(['quiz'])
            ->where('quiz_id', $quizId)
            ->where('student_id', $student->id)
            ->first();

        return response()->json(['data' => $submission]);
    }
}

