<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = Schedule::with(['course.subject', 'course.class.className', 'course.class.semester', 'course.teacher']);

        $user = $request->user();

        // Role-based automatic filtering for student or teacher
        if ($request->filled('my_schedules') && $user) {
            $userRole = $user->role ? strtolower($user->role->name) : '';

            if ($userRole === 'teacher') {
                $teacher = Teacher::where('email', $user->email)->first();
                if ($teacher) {
                    $teacherId = $teacher->id;
                    $query->whereHas('course', function ($cq) use ($teacherId) {
                        $cq->where('teacher_id', $teacherId);
                    });
                }
            } elseif ($userRole === 'student') {
                $student = Student::where('email', $user->email)->first();
                if ($student) {
                    $classIds = $student->classes()->pluck('classes.id')->toArray();
                    $query->whereHas('course', function ($cq) use ($classIds) {
                        $cq->whereIn('class_id', $classIds);
                    });
                }
            }
        }

        if ($request->filled('day')) {
            $query->where('day', strtolower($request->day));
        }

        if ($request->filled('class_id')) {
            $classId = $request->class_id;
            $query->whereHas('course', function ($cq) use ($classId) {
                $cq->where('class_id', $classId);
            });
        }

        if ($request->filled('teacher_id')) {
            $teacherId = $request->teacher_id;
            $query->whereHas('course', function ($cq) use ($teacherId) {
                $cq->where('teacher_id', $teacherId);
            });
        }

        if ($request->boolean('with_current_meet')) {
            $items = ($request->get('paginate') === 'false' || $request->get('all') === 'true')
                ? $query->orderBy('start_time', 'asc')->get()
                : $query->orderBy('start_time', 'asc')->paginate(20);

            $targetCollection = ($items instanceof \Illuminate\Pagination\LengthAwarePaginator)
                ? $items->getCollection()
                : $items;

            $targetCollection->transform(function ($item) {
                if ($item->course_id) {
                    $resolved = self::resolveCurrentMeetForCourse($item->course_id);
                    $item->current_meet = $resolved['meet'];
                    $item->current_meet_status = $resolved['status'];
                } else {
                    $item->current_meet = null;
                    $item->current_meet_status = 'none';
                }
                return $item;
            });

            return response()->json($items);
        }

        if ($request->get('paginate') === 'false' || $request->get('all') === 'true') {
            return response()->json($query->orderBy('start_time', 'asc')->get());
        }

        $schedules = $query->orderBy('start_time', 'asc')->paginate(20);

        return response()->json($schedules);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'day'         => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'room_number' => 'nullable|string|max:30',
        ]);

        $schedule = Schedule::create($validated);
        $schedule->load(['course.subject', 'course.class.className', 'course.teacher']);

        return response()->json([
            'message' => 'Jadwal pelajaran berhasil ditambahkan',
            'data'    => $schedule
        ], 201);
    }

    public function show($id)
    {
        $schedule = Schedule::with(['course.subject', 'course.class.className', 'course.teacher'])->findOrFail($id);
        return response()->json($schedule);
    }

    public function update(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $validated = $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'day'         => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'room_number' => 'nullable|string|max:30',
        ]);

        $schedule->update($validated);
        $schedule->load(['course.subject', 'course.class.className', 'course.teacher']);

        return response()->json([
            'message' => 'Jadwal pelajaran berhasil diperbarui',
            'data'    => $schedule
        ]);
    }

    public function destroy($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return response()->json([
            'message' => 'Jadwal pelajaran berhasil dihapus'
        ]);
    }

    public static function resolveCurrentMeetForCourse($courseId)
    {
        if (!$courseId) {
            return ['status' => 'none', 'meet' => null];
        }

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        // 1. Coba cari pertemuan yang start_time-nya hari ini
        $todayMeet = \App\Models\Meet::with(['chapter'])
            ->where('course_id', $courseId)
            ->whereBetween('start_time', [$todayStart, $todayEnd])
            ->orderBy('meeting_number', 'asc')
            ->first();

        if ($todayMeet) {
            return ['status' => 'today', 'meet' => $todayMeet];
        }

        // 2. Jika tidak ada pertemuan hari ini, cari pertemuan mendatang (start_time >= hari ini)
        $upcomingMeet = \App\Models\Meet::with(['chapter'])
            ->where('course_id', $courseId)
            ->where('start_time', '>=', $todayStart)
            ->orderBy('start_time', 'asc')
            ->first();

        if ($upcomingMeet) {
            return ['status' => 'upcoming', 'meet' => $upcomingMeet];
        }

        // 3. Fallback: ambil pertemuan pertama yang belum lewat atau pertemuan pertama urutan meeting_number
        $fallbackMeet = \App\Models\Meet::with(['chapter'])
            ->where('course_id', $courseId)
            ->orderBy('meeting_number', 'asc')
            ->first();

        return [
            'status' => $fallbackMeet ? 'fallback' : 'none',
            'meet'   => $fallbackMeet,
        ];
    }

    /**
     * A4: Mengambil pertemuan aktif untuk jadwal tertentu
     * (pertemuan yang start_time-nya hari ini, jika tidak ada ambil pertemuan berikutnya atau fallback).
     */
    public function currentMeet($id)
    {
        $schedule = Schedule::with(['course.subject', 'course.class.className', 'course.teacher'])->findOrFail($id);
        $course = $schedule->course;

        if (! $course) {
            return response()->json([
                'message'  => 'Kursus tidak ditemukan untuk jadwal ini.',
                'status'   => 'none',
                'data'     => null,
                'schedule' => $schedule,
            ], 404);
        }

        $result = self::resolveCurrentMeetForCourse($course->id);

        $messages = [
            'today'    => 'Pertemuan hari ini ditemukan',
            'upcoming' => 'Pertemuan mendatang ditemukan',
            'fallback' => 'Pertemuan kelas ditemukan',
            'none'     => 'Belum ada pertemuan diatur untuk kelas ini',
        ];

        return response()->json([
            'status'   => $result['status'],
            'message'  => $messages[$result['status']] ?? 'Pertemuan ditemukan',
            'data'     => $result['meet'],
            'schedule' => $schedule,
        ]);
    }
}
