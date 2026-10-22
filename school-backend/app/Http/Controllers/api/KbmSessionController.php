<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\KbmSession;
use App\Models\Schedule;
use App\Models\SessionAttendance;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KbmSessionController extends Controller
{
    /**
     * Resolves the Teacher model associated with the authenticated user
     */
    private function resolveTeacher(Request $request)
    {
        $user = $request->user();
        if (!$user) return null;

        $teacher = Teacher::where('nip', $user->username)
            ->orWhere('email', $user->email)
            ->first();

        return $teacher;
    }

    /**
     * Resolves the Student model associated with the authenticated user
     */
    private function resolveStudent(Request $request)
    {
        $user = $request->user();
        if (!$user) return null;

        $student = Student::where('nis', $user->username)
            ->orWhere('email', $user->email)
            ->first();

        return $student;
    }

    /**
     * List KBM Sessions with filtering
     */
    public function index(Request $request)
    {
        $query = KbmSession::with([
            'course.subject',
            'course.class.className',
            'schedule',
            'teacher',
            'activeMeet.chapter',
        ]);

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $sessions = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $sessions,
        ]);
    }

    /**
     * Sesi aktif hari ini (Cockpit Guru & Dashboard Siswa)
     */
    public function active(Request $request)
    {
        $user = $request->user();
        $userRole = $user && $user->role ? strtolower($user->role->name) : '';
        $today = now()->toDateString();
        $todayDay = strtolower(now()->format('l')); // monday, tuesday, etc.

        // Mode Guru
        if ($userRole === 'teacher' || $request->filled('as_teacher')) {
            $teacher = $this->resolveTeacher($request);
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data guru tidak ditemukan.',
                ], 404);
            }

            // Sesi yang sudah dibuka/dibuat hari ini
            $sessions = KbmSession::with([
                'course.subject',
                'course.class.className',
                'schedule',
                'activeMeet.chapter',
                'attendances',
            ])
                ->where('teacher_id', $teacher->id)
                ->where('date', $today)
                ->orderBy('start_time', 'asc')
                ->get();

            // Ambil jadwal rutin hari ini untuk guru ini
            $schedules = Schedule::with([
                'course.subject',
                'course.class.className',
            ])
                ->whereHas('course', function ($q) use ($teacher) {
                    $q->where('teacher_id', $teacher->id);
                })
                ->where('day', $todayDay)
                ->orderBy('start_time', 'asc')
                ->get();

            // Gabungkan info apakah jadwal sudah memiliki sesi KBM hari ini
            $scheduleSlots = $schedules->map(function ($sch) use ($sessions) {
                $matchedSession = $sessions->firstWhere('schedule_id', $sch->id);
                return [
                    'schedule'    => $sch,
                    'has_session' => (bool)$matchedSession,
                    'session'     => $matchedSession,
                ];
            });

            // Cari sesi yang sedang 'open'
            $activeOpenSession = $sessions->firstWhere('status', 'open');

            return response()->json([
                'success' => true,
                'data'    => [
                    'role'                => 'teacher',
                    'date'                => $today,
                    'day'                 => $todayDay,
                    'active_open_session' => $activeOpenSession,
                    'today_sessions'      => $sessions,
                    'today_schedules'     => $scheduleSlots,
                ]
            ]);
        }

        // Mode Siswa
        if ($userRole === 'student' || $request->filled('as_student')) {
            $student = $this->resolveStudent($request);
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data siswa tidak ditemukan.',
                ], 404);
            }

            $classIds = $student->classes()->pluck('classes.id')->toArray();
            $courseIds = Course::whereIn('class_id', $classIds)->pluck('id')->toArray();

            // Sesi KBM hari ini untuk kelas siswa
            $sessions = KbmSession::with([
                'course.subject',
                'course.class.className',
                'teacher',
                'activeMeet.chapter',
            ])
                ->whereIn('course_id', $courseIds)
                ->where('date', $today)
                ->orderBy('start_time', 'asc')
                ->get();

            // Ambil presensi siswa sendiri untuk setiap sesi
            $sessionIds = $sessions->pluck('id')->toArray();
            $myAttendances = SessionAttendance::whereIn('session_id', $sessionIds)
                ->where('student_id', $student->id)
                ->get()
                ->keyBy('session_id');

            $formattedSessions = $sessions->map(function ($s) use ($myAttendances) {
                $att = $myAttendances->get($s->id);
                $sArray = $s->toArray();
                $sArray['my_attendance'] = $att ? [
                    'id'           => $att->id,
                    'status'       => $att->status,
                    'method'       => $att->method,
                    'checkin_at'   => $att->checkin_at,
                    'status_label' => match ($att->status) {
                        'H' => 'Hadir',
                        'S' => 'Sakit',
                        'I' => 'Izin',
                        'A' => 'Alpa',
                        default => 'Belum Absen',
                    },
                ] : null;

                return $sArray;
            });

            // Cari sesi yang status open dan belum dihadiri oleh siswa
            $activeOpenSession = $formattedSessions->first(function ($s) {
                return $s['status'] === 'open';
            });

            return response()->json([
                'success' => true,
                'data'    => [
                    'role'                => 'student',
                    'date'                => $today,
                    'day'                 => $todayDay,
                    'active_open_session' => $activeOpenSession,
                    'today_sessions'      => $formattedSessions,
                ]
            ]);
        }

        // Fallback untuk Admin / Umum
        $sessions = KbmSession::with([
            'course.subject',
            'course.class.className',
            'teacher',
            'activeMeet.chapter',
        ])
            ->where('date', $today)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'role'           => 'admin',
                'date'           => $today,
                'day'            => $todayDay,
                'today_sessions' => $sessions,
            ]
        ]);
    }

    /**
     * Guru membuka sesi KBM & mengaktifkan presensi QR / Mandiri
     */
    public function open(Request $request)
    {
        $validated = $request->validate([
            'course_id'          => 'required|exists:courses,id',
            'schedule_id'        => 'nullable|exists:schedules,id',
            'active_meet_id'     => 'nullable|exists:meets,id',
            'date'               => 'nullable|date',
            'start_time'         => 'nullable|string',
            'end_time'           => 'nullable|string',
            'allow_self_checkin' => 'nullable|boolean',
            'notes'              => 'nullable|string|max:500',
        ]);

        $course = Course::with('teacher')->findOrFail($validated['course_id']);
        $teacher = $this->resolveTeacher($request) ?: $course->teacher;
        $teacherId = $teacher ? $teacher->id : $course->teacher_id;

        $date = $validated['date'] ?? now()->toDateString();
        $token = Str::random(32);

        // Cari atau buat sesi KBM untuk course_id & date ini
        $session = KbmSession::where('course_id', $course->id)
            ->where('date', $date)
            ->when(!empty($validated['schedule_id']), function ($q) use ($validated) {
                $q->where('schedule_id', $validated['schedule_id']);
            })
            ->first();

        if ($session) {
            $session->update([
                'status'             => 'open',
                'qr_token'           => $token,
                'active_meet_id'     => $validated['active_meet_id'] ?? $session->active_meet_id,
                'allow_self_checkin' => $request->has('allow_self_checkin') ? $request->boolean('allow_self_checkin') : true,
                'notes'              => $validated['notes'] ?? $session->notes,
            ]);
        } else {
            $session = KbmSession::create([
                'course_id'          => $course->id,
                'schedule_id'        => $validated['schedule_id'] ?? null,
                'teacher_id'         => $teacherId,
                'date'               => $date,
                'start_time'         => $validated['start_time'] ?? now()->format('H:i:s'),
                'end_time'           => $validated['end_time'] ?? now()->addMinutes(90)->format('H:i:s'),
                'active_meet_id'     => $validated['active_meet_id'] ?? null,
                'status'             => 'open',
                'qr_token'           => $token,
                'allow_self_checkin' => $request->boolean('allow_self_checkin', true),
                'notes'              => $validated['notes'] ?? null,
            ]);
        }

        $session->load([
            'course.subject',
            'course.class.className',
            'course.class.students',
            'schedule',
            'teacher',
            'activeMeet.chapter',
            'attendances',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sesi KBM tatap muka berhasil dibuka.',
            'data'    => $session,
        ]);
    }

    /**
     * Detail Sesi KBM beserta Roster Siswa & Rekap Presensi
     */
    public function show($id)
    {
        $session = KbmSession::with([
            'course.subject',
            'course.class.students',
            'course.class.className',
            'schedule',
            'teacher',
            'activeMeet.chapter',
            'attendances.student',
        ])->findOrFail($id);

        $students = $session->course?->class?->students ?? collect();
        $attendancesMap = $session->attendances->keyBy('student_id');

        $stats = [
            'total'      => $students->count(),
            'H'          => 0,
            'S'          => 0,
            'I'          => 0,
            'A'          => 0,
            'unrecorded' => 0,
        ];

        $roster = [];
        foreach ($students as $student) {
            $record = $attendancesMap->get($student->id);
            $status = $record ? $record->status : null;

            if ($status && isset($stats[$status])) {
                $stats[$status]++;
            } else {
                $stats['unrecorded']++;
            }

            $roster[] = [
                'student_id'   => $student->id,
                'name'         => $student->name,
                'nisn'         => $student->nisn,
                'nis'          => $student->nis,
                'status'       => $status,
                'method'       => $record?->method,
                'checkin_at'   => $record?->checkin_at,
                'notes'        => $record?->notes,
                'status_label' => match ($status) {
                    'H' => 'Hadir',
                    'S' => 'Sakit',
                    'I' => 'Izin',
                    'A' => 'Alpa',
                    default => 'Belum Absen',
                },
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'session' => $session,
                'stats'   => $stats,
                'roster'  => $roster,
            ]
        ]);
    }

    /**
     * Guru menautkan topik / Sub-bab aktif pada sesi KBM
     */
    public function setTopic(Request $request, $id)
    {
        $session = KbmSession::findOrFail($id);

        $validated = $request->validate([
            'active_meet_id' => 'nullable|exists:meets,id',
            'notes'          => 'nullable|string|max:500',
        ]);

        $session->update([
            'active_meet_id' => $validated['active_meet_id'] ?? null,
            'notes'          => $validated['notes'] ?? $session->notes,
        ]);

        $session->load(['activeMeet.chapter', 'course.subject']);

        return response()->json([
            'success' => true,
            'message' => 'Topik pembelajaran KBM berhasil diperbarui.',
            'data'    => $session,
        ]);
    }

    /**
     * Siswa Presensi Mandiri (Dinonaktifkan - Presensi dikelola penuh oleh guru)
     */
    public function checkIn(Request $request, $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Fitur presensi mandiri siswa telah dinonaktifkan. Kehadiran siswa dicatat langsung oleh guru di kelas.',
        ], 403);
    }


    /**
     * Guru Bulk Attendance (1-Tap Hadirkan Semua atau Update Massal H/S/I/A)
     */
    public function batchAttendance(Request $request, $id)
    {
        $session = KbmSession::with('course.class.students')->findOrFail($id);

        $stats = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

        // Mode 1: Hadirkan Semua (1-Tap Mark All Present)
        if ($request->filled('mark_all')) {
            $targetStatus = strtoupper($request->input('mark_all'));
            if (!in_array($targetStatus, ['H', 'S', 'I', 'A'])) {
                $targetStatus = 'H';
            }

            $students = $session->course?->class?->students ?? collect();

            DB::transaction(function () use ($session, $students, $targetStatus, &$stats) {
                foreach ($students as $student) {
                    SessionAttendance::updateOrCreate(
                        [
                            'session_id' => $session->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'status'     => $targetStatus,
                            'method'     => 'manual_teacher',
                            'checkin_at' => now(),
                        ]
                    );
                    $stats[$targetStatus]++;
                }
            });

            return response()->json([
                'success' => true,
                'message' => "Seluruh siswa kelas ({$students->count()} siswa) berhasil ditandai '{$targetStatus}'.",
                'data'    => [
                    'stats' => $stats,
                ]
            ]);
        }

        // Mode 2: Array presensi spesifik dari tabel guru
        $validated = $request->validate([
            'attendances'              => 'required|array',
            'attendances.*.student_id' => 'required|exists:students,id',
            'attendances.*.status'     => 'required|in:H,S,I,A',
            'attendances.*.notes'      => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($session, $validated, &$stats) {
            foreach ($validated['attendances'] as $att) {
                SessionAttendance::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'student_id' => $att['student_id'],
                    ],
                    [
                        'status'     => $att['status'],
                        'method'     => 'manual_teacher',
                        'notes'      => $att['notes'] ?? null,
                        'checkin_at' => now(),
                    ]
                );

                if (isset($stats[$att['status']])) {
                    $stats[$att['status']]++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Presensi siswa berhasil diperbarui.',
            'data'    => [
                'stats' => $stats,
            ]
        ]);
    }

    /**
     * Guru menutup sesi KBM tatap muka
     */
    public function close(Request $request, $id)
    {
        $session = KbmSession::with('course.class.students')->findOrFail($id);

        $markUnrecordedAsAlpa = $request->boolean('mark_unrecorded_as_alpa', true);

        if ($markUnrecordedAsAlpa && $session->course?->class) {
            $existingAttStudentIds = SessionAttendance::where('session_id', $session->id)
                ->pluck('student_id')
                ->toArray();

            $allStudents = $session->course->class->students;
            foreach ($allStudents as $student) {
                if (!in_array($student->id, $existingAttStudentIds)) {
                    SessionAttendance::create([
                        'session_id' => $session->id,
                        'student_id' => $student->id,
                        'status'     => 'A',
                        'method'     => 'manual_teacher',
                        'notes'      => 'Alpa (tidak presensi hingga sesi berakhir)',
                        'checkin_at' => null,
                    ]);
                }
            }
        }

        $session->update([
            'status'   => 'completed',
            'qr_token' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sesi KBM selesai dan presensi berhasil direkapitulasi.',
            'data'    => $session,
        ]);
    }

    /**
     * Regenerasi token QR Code (agar proyektor selalu up-to-date)
     */
    public function regenerateQr($id)
    {
        $session = KbmSession::findOrFail($id);

        if ($session->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya sesi berstatus open yang dapat meregenerasi QR token.',
            ], 422);
        }

        $newToken = Str::random(32);
        $session->update(['qr_token' => $newToken]);

        return response()->json([
            'success' => true,
            'message' => 'QR Code berhasil diregenerasi.',
            'data'    => [
                'session_id' => $session->id,
                'qr_token'   => $newToken,
            ]
        ]);
    }
}
