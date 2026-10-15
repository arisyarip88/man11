<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Meet;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    /**
     * A2: Menampilkan daftar bab untuk suatu kursus beserta pertemuannya.
     */
    public function index(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        $chapters = Chapter::where('course_id', $courseId)
            ->with(['meets' => function ($q) {
                $q->withCount(['materials', 'quizzes'])->orderBy('meeting_number', 'asc');
            }])
            ->orderBy('order', 'asc')
            ->get();

        // Ambil pertemuan tanpa bab (unassigned meets) agar kompatibel dengan data lama
        $unassignedMeets = Meet::where('course_id', $courseId)
            ->whereNull('chapter_id')
            ->withCount(['materials', 'quizzes'])
            ->orderBy('meeting_number', 'asc')
            ->get();

        return response()->json([
            'chapters'         => $chapters,
            'unassigned_meets' => $unassignedMeets,
            'course'           => [
                'id'           => $course->id,
                'subject_name' => $course->subject?->name,
            ],
        ]);
    }

    /**
     * A2: Membuat bab baru di dalam kursus.
     */
    public function store(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        // Otorisasi: Guru pengampu atau admin
        if (! $user->isAdmin()) {
            $teacher = $user->teacher;
            if (! $teacher || $course->teacher_id !== $teacher->id) {
                return response()->json(['message' => 'Anda tidak berhak menambah bab pada kursus ini.'], 403);
            }
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
        ]);

        if (! isset($validated['order']) || is_null($validated['order'])) {
            $maxOrder = Chapter::where('course_id', $courseId)->max('order') ?? 0;
            $validated['order'] = $maxOrder + 1;
        }

        $validated['course_id'] = $courseId;
        $chapter = Chapter::create($validated);

        return response()->json([
            'message' => 'Bab berhasil dibuat',
            'data'    => $chapter->load('meets'),
        ], 201);
    }

    /**
     * A2: Memperbarui detail bab.
     */
    public function update(Request $request, $courseId, $chapterId = null)
    {
        // Support URL /courses/{courseId}/chapters/{chapterId} atau /chapters/{id}
        $targetId = $chapterId ?: $courseId;
        $chapter = Chapter::with('course')->findOrFail($targetId);
        $course = $chapter->course;
        $user = $request->user();

        if (! $user->isAdmin()) {
            $teacher = $user->teacher;
            if (! $teacher || $course->teacher_id !== $teacher->id) {
                return response()->json(['message' => 'Anda tidak berhak memperbarui bab ini.'], 403);
            }
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'order'       => 'nullable|integer',
        ]);

        $chapter->update($validated);

        return response()->json([
            'message' => 'Bab berhasil diperbarui',
            'data'    => $chapter->load('meets'),
        ]);
    }

    /**
     * A2: Menghapus bab (pertemuan di dalamnya menjadi chapter_id = null).
     */
    public function destroy(Request $request, $courseId, $chapterId = null)
    {
        $targetId = $chapterId ?: $courseId;
        $chapter = Chapter::with('course')->findOrFail($targetId);
        $course = $chapter->course;
        $user = $request->user();

        if (! $user->isAdmin()) {
            $teacher = $user->teacher;
            if (! $teacher || $course->teacher_id !== $teacher->id) {
                return response()->json(['message' => 'Anda tidak berhak menghapus bab ini.'], 403);
            }
        }

        $chapter->delete();

        return response()->json([
            'message' => 'Bab berhasil dihapus. Pertemuan di dalamnya kini berstatus tanpa bab.',
        ]);
    }

    /**
     * A6: Mengurutkan urutan bab (Reorder).
     */
    public function reorder(Request $request, $courseId)
    {
        $validated = $request->validate([
            'orders'          => 'required|array',
            'orders.*.id'    => 'required|exists:chapters,id',
            'orders.*.order' => 'required|integer',
        ]);

        foreach ($validated['orders'] as $item) {
            Chapter::where('id', $item['id'])
                ->where('course_id', $courseId)
                ->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Urutan bab berhasil diperbarui']);
    }

    /**
     * A6: Memindahkan beberapa pertemuan ke bab tertentu.
     */
    public function assignMeets(Request $request, $chapterId)
    {
        $chapter = Chapter::findOrFail($chapterId);
        $validated = $request->validate([
            'meet_ids'   => 'required|array',
            'meet_ids.*' => 'exists:meets,id',
        ]);

        Meet::whereIn('id', $validated['meet_ids'])
            ->where('course_id', $chapter->course_id)
            ->update(['chapter_id' => $chapter->id]);

        return response()->json([
            'message' => 'Pertemuan berhasil dipindahkan ke ' . $chapter->title,
        ]);
    }
}
