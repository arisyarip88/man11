<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meet;
use App\Models\MeetAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Mengambil daftar presensi siswa pada suatu pertemuan
     */
    public function getMeetAttendances(Request $request, $meetId)
    {
        $meet = Meet::with(['course.class.students', 'course.class.className', 'course.subject'])->findOrFail($meetId);
        $class = $meet->course?->class;

        if (!$class) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas pada pertemuan ini tidak ditemukan.'
            ], 404);
        }

        $existingAttendances = MeetAttendance::where('meet_id', $meet->id)
            ->get()
            ->keyBy('student_id');

        $students = $class->students;
        $stats = [
            'total' => $students->count(),
            'H'     => 0,
            'S'     => 0,
            'I'     => 0,
            'A'     => 0,
        ];

        $list = [];
        foreach ($students as $student) {
            $record = $existingAttendances->get($student->id);
            $status = $record ? $record->status : 'H'; // Default Hadir
            $notes = $record ? $record->notes : null;

            if (isset($stats[$status])) {
                $stats[$status]++;
            }

            $list[] = [
                'student_id' => $student->id,
                'name'       => $student->name,
                'nisn'       => $student->nisn,
                'status'     => $status,
                'notes'      => $notes,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'meet'        => [
                    'id'             => $meet->id,
                    'meeting_number' => $meet->meeting_number,
                    'title'          => $meet->title,
                    'subject_name'   => $meet->course?->subject?->name,
                    'class_name'     => $class->className?->name ?: 'Kelas',
                ],
                'stats'       => $stats,
                'attendances' => $list,
            ]
        ]);
    }

    /**
     * Batch simpan presensi satu kelas sekaligus
     */
    public function batchSaveMeetAttendances(Request $request, $meetId)
    {
        $meet = Meet::findOrFail($meetId);

        $validated = $request->validate([
            'attendances'              => 'required|array',
            'attendances.*.student_id' => 'required|exists:students,id',
            'attendances.*.status'     => 'required|in:H,S,I,A',
            'attendances.*.notes'      => 'nullable|string|max:255',
        ]);

        $teacherId = null;
        if ($request->user() && $request->user()->teacher) {
            $teacherId = $request->user()->teacher->id;
        }

        if (!$teacherId) {
            $firstTeacher = \App\Models\Teacher::first();
            $teacherId = $firstTeacher ? $firstTeacher->id : 1;
        }

        $stats = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

        DB::transaction(function () use ($validated, $meet, $teacherId, &$stats) {
            foreach ($validated['attendances'] as $att) {
                MeetAttendance::updateOrCreate(
                    [
                        'meet_id'    => $meet->id,
                        'student_id' => $att['student_id'],
                    ],
                    [
                        'status'      => $att['status'],
                        'notes'       => $att['notes'] ?? null,
                        'recorded_by' => $teacherId,
                    ]
                );

                if (isset($stats[$att['status']])) {
                    $stats[$att['status']]++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Presensi pertemuan berhasil disimpan.',
            'data'    => [
                'stats' => $stats,
            ]
        ]);
    }

    /**
     * Mengambil status presensi siswa yang sedang login pada pertemuan ini
     */
    public function getMyAttendance(Request $request, $meetId)
    {
        $meet = Meet::findOrFail($meetId);
        $user = $request->user();

        $student = null;
        if ($user) {
            $student = \App\Models\Student::where('nis', $user->username)->orWhere('email', $user->email)->first();
        }

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Akun siswa tidak ditemukan.'
            ], 404);
        }

        $attendance = MeetAttendance::where('meet_id', $meet->id)
            ->where('student_id', $student->id)
            ->first();

        $labels = [
            'H' => 'Hadir',
            'S' => 'Sakit',
            'I' => 'Izin',
            'A' => 'Alpa',
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'meet_id'      => $meet->id,
                'student_id'   => $student->id,
                'status'       => $attendance ? $attendance->status : null,
                'status_label' => $attendance ? ($labels[$attendance->status] ?? $attendance->status) : 'Belum Presensi',
                'notes'        => $attendance?->notes,
                'recorded_at'  => $attendance?->updated_at,
            ]
        ]);
    }

    /**
     * Siswa melakukan presensi mandiri (Dinonaktifkan - Presensi dicatat langsung oleh guru)
     */
    public function selfCheckIn(Request $request, $meetId)
    {
        return response()->json([
            'success' => false,
            'message' => 'Fitur presensi mandiri siswa telah dinonaktifkan. Kehadiran siswa dicatat langsung oleh guru di kelas.',
        ], 403);
    }


    /**
     * Rekap Presensi Siswa secara Kumulatif Lintas Seluruh Kelas & Pertemuan
     */
    public function getStudentAttendanceSummary(Request $request)
    {
        $user = $request->user();
        $student = $user?->student ?: \App\Models\Student::where('email', $user?->email)->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Profil siswa tidak ditemukan.'
            ], 404);
        }

        // Ambil presensi dari MeetAttendance (lama) dan SessionAttendance (baru KbmSession)
        $meetAttendances = MeetAttendance::where('student_id', $student->id)
            ->with(['meet.course.subject', 'meet.course.class.className'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $sessionAttendances = \App\Models\SessionAttendance::where('student_id', $student->id)
            ->with([
                'session.course.subject',
                'session.course.class.className',
                'session.activeMeet',
            ])
            ->orderBy('checkin_at', 'desc')
            ->get();

        $records = collect();

        // 1. Petakan record SessionAttendance (KbmSession modern)
        foreach ($sessionAttendances as $sAtt) {
            $sess = $sAtt->session;
            $records->push([
                'id'             => 'sess_' . $sAtt->id,
                'session_id'     => $sAtt->session_id,
                'meet_id'        => $sess?->active_meet_id,
                'status'         => $sAtt->status,
                'method'         => $sAtt->method,
                'notes'          => $sAtt->notes,
                'recorded_at'    => $sAtt->checkin_at ? $sAtt->checkin_at->toDateTimeString() : ($sAtt->updated_at ? $sAtt->updated_at->toDateTimeString() : null),
                'meeting_title'  => $sess?->activeMeet?->title ?: ('Sesi KBM (' . ($sess?->date ? $sess->date->format('d/m/Y') : 'Tatap Muka') . ')'),
                'meeting_number' => $sess?->activeMeet?->meeting_number ?: null,
                'subject_name'   => $sess?->course?->subject?->name ?: 'Mata Pelajaran',
                'class_name'     => $sess?->course?->class?->className?->name ?: 'Kelas',
                'type'           => 'kbm_session',
            ]);
        }

        // 2. Petakan record MeetAttendance (Legacy meet)
        foreach ($meetAttendances as $att) {
            $records->push([
                'id'             => 'meet_' . $att->id,
                'session_id'     => null,
                'meet_id'        => $att->meet_id,
                'status'         => $att->status,
                'method'         => 'meet_checkin',
                'notes'          => $att->notes,
                'recorded_at'    => $att->updated_at ? $att->updated_at->toDateTimeString() : null,
                'meeting_title'  => $att->meet?->title ?: 'Pertemuan',
                'meeting_number' => $att->meet?->meeting_number,
                'subject_name'   => $att->meet?->course?->subject?->name ?: 'Mata Pelajaran',
                'class_name'     => $att->meet?->course?->class?->className?->name ?: 'Kelas',
                'type'           => 'legacy_meet',
            ]);
        }

        // Urutkan berdasarkan waktu pencatatan terbaru
        $sortedRecords = $records->sortByDesc('recorded_at')->values();

        $stats = [
            'total' => $sortedRecords->count(),
            'H'     => $sortedRecords->where('status', 'H')->count(),
            'S'     => $sortedRecords->where('status', 'S')->count(),
            'I'     => $sortedRecords->where('status', 'I')->count(),
            'A'     => $sortedRecords->where('status', 'A')->count(),
        ];

        $attendanceRate = $stats['total'] > 0
            ? round(($stats['H'] / $stats['total']) * 100, 1)
            : 100;

        return response()->json([
            'success' => true,
            'data'    => [
                'student'         => [
                    'id'   => $student->id,
                    'name' => $student->name,
                    'nis'  => $student->nis,
                    'nisn' => $student->nisn,
                ],
                'stats'           => $stats,
                'attendance_rate' => $attendanceRate,
                'records'         => $sortedRecords,
            ]
        ]);
    }
}
