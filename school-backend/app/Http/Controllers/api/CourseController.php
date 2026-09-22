<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['class.className', 'class.semester', 'subject', 'teacher'])
            ->withCount('meets');

        $user = $request->user();

        // Role-based automatic filtering if requested or by default for student/teacher
        if ($request->filled('my_courses') && $user) {
            $userRole = $user->role ? strtolower($user->role->name) : '';

            if ($userRole === 'teacher') {
                $teacher = Teacher::where('email', $user->email)->first();
                if ($teacher) {
                    $query->where('teacher_id', $teacher->id);
                } else {
                    return response()->json([]);
                }
            } elseif ($userRole === 'student') {
                $student = Student::where('email', $user->email)->first();
                if ($student) {
                    $classIds = $student->classes()->pluck('classes.id')->toArray();
                    $query->whereIn('class_id', $classIds);
                } else {
                    return response()->json([]);
                }
            }
        }

        // Direct filters
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('semester_id')) {
            $semesterId = $request->semester_id;
            $query->whereHas('class', function ($cq) use ($semesterId) {
                $cq->where('semester_id', $semesterId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('subject', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                })->orWhereHas('teacher', function ($tq) use ($search) {
                    $tq->where('name', 'like', "%{$search}%");
                })->orWhereHas('class.className', function ($cnq) use ($search) {
                    $cnq->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->get('paginate') === 'false' || $request->get('all') === 'true') {
            return response()->json($query->orderBy('id', 'desc')->get());
        }

        $courses = $query->orderBy('id', 'desc')->paginate(10);

        return response()->json($courses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id'     => 'required|exists:classes,id',
            'subject_id'   => 'required|exists:subjects,id',
            'teacher_id'   => 'required|exists:teachers,id',
            'credit_hours' => 'required|integer|min:1|max:10',
        ]);

        $exists = Course::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Mata pelajaran ini sudah diplotkan untuk kelas tersebut'
            ], 422);
        }

        $course = Course::create($validated);
        $course->load(['class.className', 'class.semester', 'subject', 'teacher']);

        return response()->json([
            'message' => 'Pengalokasian pengajaran (Course) berhasil ditambahkan',
            'data'    => $course
        ], 201);
    }

    public function show($id)
    {
        $course = Course::with([
            'class.className', 
            'class.semester', 
            'class.students',
            'subject', 
            'teacher', 
            'meets.materials', 
            'meets.quizzes'
        ])
        ->withCount('meets')
        ->findOrFail($id);

        return response()->json($course);
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'class_id'     => 'required|exists:classes,id',
            'subject_id'   => 'required|exists:subjects,id',
            'teacher_id'   => 'required|exists:teachers,id',
            'credit_hours' => 'required|integer|min:1|max:10',
        ]);

        $exists = Course::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('id', '!=', $course->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Mata pelajaran ini sudah diplotkan untuk kelas tersebut'
            ], 422);
        }

        $course->update($validated);
        $course->load(['class.className', 'class.semester', 'subject', 'teacher']);

        return response()->json([
            'message' => 'Pengalokasian pengajaran berhasil diperbarui',
            'data'    => $course
        ]);
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return response()->json([
            'message' => 'Pengalokasian pengajaran berhasil dihapus'
        ]);
    }
}
