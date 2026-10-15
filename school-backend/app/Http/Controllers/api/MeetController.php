<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meet;
use Illuminate\Http\Request;

class MeetController extends Controller
{
    public function index(Request $request)
    {
        $query = Meet::with(['course.subject', 'course.class.className', 'course.teacher', 'chapter'])
            ->withCount(['materials', 'quizzes', 'discussions']);

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('chapter_id')) {
            if ($request->chapter_id === 'unassigned' || $request->chapter_id === 'null') {
                $query->whereNull('chapter_id');
            } else {
                $query->where('chapter_id', $request->chapter_id);
            }
        }

        $user = $request->user();
        $studentId = null;
        if ($user && in_array(strtolower($user->role?->name ?? ''), ['student', 'siswa'])) {
            $student = \App\Models\Student::where('nis', $user->username)->orWhere('email', $user->email)->first();
            if (!$student) {
                $student = \App\Models\Student::first();
            }
            $studentId = $student?->id;
        }

        if ($request->boolean('with_details')) {
            $query->with([
                'materials',
                'chapter',
                'quizzes.submissions' => function ($sq) use ($studentId) {
                    if ($studentId) {
                        $sq->where('student_id', $studentId);
                    }
                },
                'discussions' => function ($dq) {
                    $dq->whereNull('parent_id')
                       ->with(['student', 'teacher', 'replies.student', 'replies.teacher'])
                       ->orderBy('created_at', 'desc');
                }
            ]);
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
            'chapter_id'     => 'nullable|exists:chapters,id',
            'meeting_number' => 'required|integer|min:1',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'start_time'     => 'nullable|date',
            'end_time'       => 'nullable|date|after_or_equal:start_time',
            'type'           => 'required|in:online,offline',
            'meeting_link'   => 'nullable|url|max:255',
        ]);

        $meet = Meet::create($validated);
        $meet->load(['course.subject', 'course.class.className', 'course.teacher', 'chapter']);

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
            'chapter',
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
            'chapter_id'     => 'nullable|exists:chapters,id',
            'meeting_number' => 'required|integer|min:1',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'start_time'     => 'nullable|date',
            'end_time'       => 'nullable|date|after_or_equal:start_time',
            'type'           => 'required|in:online,offline',
            'meeting_link'   => 'nullable|url|max:255',
        ]);

        $meet->update($validated);
        $meet->load(['course.subject', 'course.class.className', 'course.teacher', 'chapter']);

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

    /**
     * A1: Duplikasi Pertemuan (Meet) beserta Materi dan Kuis
     */
    public function duplicate(Request $request, $id)
    {
        $sourceMeet = Meet::with(['course', 'materials', 'quizzes'])->findOrFail($id);
        $user = $request->user();

        // A5: Otorisasi: hanya admin atau guru pemilik course
        if (! $user->isAdmin()) {
            $teacher = $user->teacher;
            if (! $teacher || $sourceMeet->course->teacher_id !== $teacher->id) {
                return response()->json(['message' => 'Anda tidak memiliki hak akses untuk menduplikat pertemuan ini.'], 403);
            }
        }

        $newMeet = \Illuminate\Support\Facades\DB::transaction(function () use ($sourceMeet) {
            $maxMeetingNumber = Meet::where('course_id', $sourceMeet->course_id)->max('meeting_number') ?? 0;
            $newMeetingNumber = $maxMeetingNumber + 1;

            $newMeet = Meet::create([
                'course_id'      => $sourceMeet->course_id,
                'meeting_number' => $newMeetingNumber,
                'title'          => $sourceMeet->title . ' (Salinan)',
                'description'    => $sourceMeet->description,
                'type'           => $sourceMeet->type,
                'meeting_link'   => $sourceMeet->meeting_link,
                'start_time'     => null,
                'end_time'       => null,
            ]);

            // A3: Salin Materi (referensikan file_path yang sama untuk hemat storage)
            foreach ($sourceMeet->materials as $mat) {
                \App\Models\Material::create([
                    'meet_id'      => $newMeet->id,
                    'title'        => $mat->title,
                    'type'         => $mat->type,
                    'content'      => $mat->content,
                    'file_path'    => $mat->file_path,
                    'external_url' => $mat->external_url,
                ]);
            }

            // Salin Kuis (generate token baru, jangan salin submission)
            foreach ($sourceMeet->quizzes as $quiz) {
                $newToken = $quiz->access_token ? strtoupper(\Illuminate\Support\Str::random(6)) : null;

                \App\Models\Quiz::create([
                    'meet_id'            => $newMeet->id,
                    'title'              => $quiz->title,
                    'instructions'       => $quiz->instructions,
                    'quiz_type'          => $quiz->quiz_type,
                    'start_time'         => null,
                    'end_time'           => null,
                    'due_date'           => null,
                    'max_score'          => $quiz->max_score,
                    'duration_minutes'   => $quiz->duration_minutes,
                    'is_random_question' => $quiz->is_random_question,
                    'is_random_option'   => $quiz->is_random_option,
                    'question_bank_id'   => $quiz->question_bank_id,
                    'questions_data'     => $quiz->questions_data,
                    'access_token'       => $newToken,
                    'token_expires_at'   => null,
                    'available_from'     => null,
                    'available_until'    => null,
                    'max_violations'     => $quiz->max_violations,
                ]);
            }

            return $newMeet;
        });

        $newMeet->load(['materials', 'quizzes', 'course.subject', 'course.class.className', 'course.teacher']);

        return response()->json([
            'message' => 'Pertemuan berhasil diduplikasi.',
            'data'    => $newMeet
        ], 201);
    }
}
