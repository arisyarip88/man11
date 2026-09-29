<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterCourse;
use App\Models\Course;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MasterCourseController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterCourse::with(['subject', 'semester', 'teacher', 'rombels.className']);

        // Jika user adalah guru, prioritaskan master course miliknya sendiri
        if ($request->user() && $request->user()->teacher) {
            $query->where('teacher_id', $request->user()->teacher->id);
        } elseif ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->get('paginate') === 'false' || $request->get('all') === 'true') {
            return response()->json([
                'success' => true,
                'data' => $query->orderBy('id', 'desc')->get()
            ]);
        }

        $items = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject_id'   => 'required|exists:subjects,id',
            'semester_id'  => 'required|exists:semesters,id',
            'grade_level'  => 'required|string|in:10,11,12',
            'name'         => 'required|string|max:255',
            'code'         => 'nullable|string|max:50|unique:master_courses,code',
            'description'  => 'nullable|string',
            'teacher_id'   => 'nullable|exists:teachers,id',
            'rombel_ids'   => 'nullable|array',
            'rombel_ids.*' => 'exists:classes,id',
        ]);

        $teacherId = $validated['teacher_id'] ?? null;
        if (!$teacherId && $request->user() && $request->user()->teacher) {
            $teacherId = $request->user()->teacher->id;
        }

        if (!$teacherId) {
            // Ambil guru pertama jika tidak dispesifikasikan (fallback development)
            $firstTeacher = \App\Models\Teacher::first();
            $teacherId = $firstTeacher ? $firstTeacher->id : 1;
        }

        $validated['teacher_id'] = $teacherId;

        if (empty($validated['code'])) {
            $year = date('Y');
            $validated['code'] = 'KM-' . $year . '-' . strtoupper(Str::random(4));
        }

        return DB::transaction(function () use ($validated) {
            $rombelIds = $validated['rombel_ids'] ?? [];
            unset($validated['rombel_ids']);

            $masterCourse = MasterCourse::create($validated);

            if (!empty($rombelIds)) {
                $syncData = [];
                foreach ($rombelIds as $rId) {
                    $syncData[$rId] = ['synced_at' => now()];
                }
                $masterCourse->rombels()->sync($syncData);

                // Buat course otomatis pada tiap rombel terhubung jika belum ada
                foreach ($rombelIds as $rId) {
                    Course::firstOrCreate(
                        [
                            'class_id'   => $rId,
                            'subject_id' => $masterCourse->subject_id,
                            'teacher_id' => $masterCourse->teacher_id,
                        ],
                        [
                            'credit_hours' => 2,
                        ]
                    );
                }
            }

            $masterCourse->load(['subject', 'semester', 'teacher', 'rombels.className']);

            return response()->json([
                'success' => true,
                'message' => 'Kelas Master berhasil dibuat',
                'data'    => $masterCourse
            ], 201);
        });
    }

    public function show($id)
    {
        $masterCourse = MasterCourse::with(['subject', 'semester', 'teacher', 'rombels.className'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $masterCourse
        ]);
    }

    public function update(Request $request, $id)
    {
        $masterCourse = MasterCourse::findOrFail($id);

        $validated = $request->validate([
            'subject_id'   => 'required|exists:subjects,id',
            'semester_id'  => 'required|exists:semesters,id',
            'grade_level'  => 'required|string|in:10,11,12',
            'name'         => 'required|string|max:255',
            'code'         => 'required|string|max:50|unique:master_courses,code,' . $masterCourse->id,
            'description'  => 'nullable|string',
            'is_active'    => 'nullable|boolean',
        ]);

        $masterCourse->update($validated);
        $masterCourse->load(['subject', 'semester', 'teacher', 'rombels.className']);

        return response()->json([
            'success' => true,
            'message' => 'Kelas Master berhasil diperbarui',
            'data'    => $masterCourse
        ]);
    }

    public function destroy($id)
    {
        $masterCourse = MasterCourse::findOrFail($id);
        $masterCourse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kelas Master berhasil dihapus'
        ]);
    }

    /**
     * Sinkronisasi Kelas Master ke Rombel-Rombel Terpilih
     */
    public function syncRombels(Request $request, $id)
    {
        $masterCourse = MasterCourse::findOrFail($id);

        $validated = $request->validate([
            'rombel_ids'   => 'required|array',
            'rombel_ids.*' => 'exists:classes,id',
        ]);

        return DB::transaction(function () use ($masterCourse, $validated) {
            $rombelIds = $validated['rombel_ids'];
            $syncData = [];
            $now = now();

            foreach ($rombelIds as $rId) {
                $syncData[$rId] = ['synced_at' => $now];
            }

            $masterCourse->rombels()->sync($syncData);

            // Pastikan setiap rombel memiliki record Course aktif untuk mata pelajaran & guru ini
            $createdCourses = [];
            foreach ($rombelIds as $rId) {
                $course = Course::firstOrCreate(
                    [
                        'class_id'   => $rId,
                        'subject_id' => $masterCourse->subject_id,
                        'teacher_id' => $masterCourse->teacher_id,
                    ],
                    [
                        'credit_hours' => 2,
                    ]
                );
                $createdCourses[] = $course->id;
            }

            $masterCourse->load(['subject', 'semester', 'teacher', 'rombels.className']);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menyinkronkan Kelas Master ke ' . count($rombelIds) . ' rombel terhubung',
                'data'    => [
                    'master_course'   => $masterCourse,
                    'synced_rombels'  => count($rombelIds),
                    'course_ids'      => $createdCourses,
                ]
            ]);
        });
    }
}
