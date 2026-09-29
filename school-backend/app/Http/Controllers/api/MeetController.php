<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meet;
use Illuminate\Http\Request;

class MeetController extends Controller
{
    public function index(Request $request)
    {
        $query = Meet::with(['course.subject', 'course.class.className', 'course.teacher'])
            ->withCount(['materials', 'quizzes', 'discussions']);

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->boolean('with_details')) {
            $query->with(['materials', 'quizzes']);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->get('paginate') === 'false' || $request->get('all') === 'true') {
            return response()->json($query->orderBy('meeting_number', 'asc')->get());
        }

        $meets = $query->orderBy('meeting_number', 'asc')->paginate(15);

        return response()->json($meets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id'      => 'required|exists:courses,id',
            'meeting_number' => 'required|integer|min:1',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'start_time'     => 'nullable|date',
            'end_time'       => 'nullable|date|after_or_equal:start_time',
            'type'           => 'required|in:online,offline',
            'meeting_link'   => 'nullable|url|max:255',
        ]);

        $meet = Meet::create($validated);
        $meet->load(['course.subject', 'course.class.className', 'course.teacher']);

        return response()->json([
            'message' => 'Sesi Pertemuan berhasil dibuat',
            'data'    => $meet
        ], 201);
    }

    public function show($id)
    {
        $user = request()->user();
        $studentId = null;
        if ($user && in_array(strtolower($user->role?->name ?? ''), ['student', 'siswa'])) {
            $student = \App\Models\Student::where('nis', $user->username)->orWhere('email', $user->email)->first();
            $studentId = $student?->id;
        }

        $meet = Meet::with([
            'course.subject', 
            'course.class.className', 
            'course.teacher', 
            'materials', 
            'quizzes.submissions' => function ($sq) use ($studentId) {
                if ($studentId) {
                    $sq->where('student_id', $studentId);
                }
            },
            'discussions' => function ($q) {
                $q->whereNull('parent_id')->with(['replies.student', 'replies.teacher', 'student', 'teacher']);
            }
        ])->findOrFail($id);

        $myAttendance = null;
        if ($studentId) {
            $attRecord = \App\Models\MeetAttendance::where('meet_id', $id)
                ->where('student_id', $studentId)
                ->first();
            $labels = ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa'];
            $myAttendance = [
                'status'       => $attRecord ? $attRecord->status : null,
                'status_label' => $attRecord ? ($labels[$attRecord->status] ?? $attRecord->status) : 'Belum Presensi',
                'notes'        => $attRecord?->notes,
                'recorded_at'  => $attRecord?->updated_at,
            ];
        }

        $meetArray = $meet->toArray();
        $meetArray['my_attendance'] = $myAttendance;

        return response()->json($meetArray);
    }

    public function update(Request $request, $id)
    {
        $meet = Meet::findOrFail($id);

        $validated = $request->validate([
            'course_id'      => 'required|exists:courses,id',
            'meeting_number' => 'required|integer|min:1',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'start_time'     => 'nullable|date',
            'end_time'       => 'nullable|date|after_or_equal:start_time',
            'type'           => 'required|in:online,offline',
            'meeting_link'   => 'nullable|url|max:255',
        ]);

        $meet->update($validated);
        $meet->load(['course.subject', 'course.class.className', 'course.teacher']);

        return response()->json([
            'message' => 'Sesi Pertemuan berhasil diperbarui',
            'data'    => $meet
        ]);
    }

    public function destroy($id)
    {
        $meet = Meet::findOrFail($id);
        $meet->delete();

        return response()->json([
            'message' => 'Sesi Pertemuan berhasil dihapus'
        ]);
    }
}
