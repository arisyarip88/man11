<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\QuizSubmission;
use App\Models\Student;
use Illuminate\Http\Request;

class ScoreController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::query();

        if ($request->filled('class_id')) {
            $classId = $request->class_id;
            $query->whereHas('classes', function ($cq) use ($classId) {
                $cq->where('classes.id', $classId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        $students = $query->with(['submissions.quiz.meet.course.subject'])->get();

        $scoresReport = $students->map(function ($student) {
            $gradedSubmissions = $student->submissions->where('status', 'graded');
            $avgScore = $gradedSubmissions->count() > 0 ? round($gradedSubmissions->avg('score'), 2) : 0;

            return [
                'student_id'            => $student->id,
                'name'                  => $student->name,
                'nisn'                  => $student->nisn,
                'total_submissions'     => $student->submissions->count(),
                'graded_submissions'    => $gradedSubmissions->count(),
                'average_score'         => $avgScore,
                'grade_status'          => $avgScore >= 75 ? 'Lulus' : ($gradedSubmissions->count() > 0 ? 'Perlu Remidi' : 'Belum Ada Nilai'),
            ];
        });

        return response()->json([
            'total' => $scoresReport->count(),
            'data'  => $scoresReport
        ]);
    }

    public function studentScores(Request $request, $id)
    {
        $student = Student::with(['classes.className', 'submissions.quiz.meet.course.subject'])->findOrFail($id);

        $gradedSubmissions = $student->submissions->where('status', 'graded');
        $avgScore = $gradedSubmissions->count() > 0 ? round($gradedSubmissions->avg('score'), 2) : 0;

        return response()->json([
            'student'            => [
                'id'    => $student->id,
                'name'  => $student->name,
                'nisn'  => $student->nisn,
                'email' => $student->email,
            ],
            'average_score'      => $avgScore,
            'submissions'        => $student->submissions->map(function ($sub) {
                return [
                    'id'               => $sub->id,
                    'quiz_title'       => $sub->quiz ? $sub->quiz->title : '-',
                    'quiz_type'        => $sub->quiz ? $sub->quiz->quiz_type : '-',
                    'subject'          => ($sub->quiz && $sub->quiz->meet && $sub->quiz->meet->course && $sub->quiz->meet->course->subject) ? $sub->quiz->meet->course->subject->name : '-',
                    'submitted_at'     => $sub->submitted_at,
                    'score'            => $sub->score,
                    'teacher_feedback' => $sub->teacher_feedback,
                    'status'           => $sub->status,
                ];
            })
        ]);
    }
}
