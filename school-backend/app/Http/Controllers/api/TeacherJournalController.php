<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeacherJournalController extends Controller
{
    public function index(Request $request)
    {
        $query = TeacherJournal::with(['teacher', 'rombel.className', 'subject', 'meet', 'verifier']);

        // Jika user adalah guru, prioritaskan jurnal miliknya sendiri
        if ($request->user() && $request->user()->teacher) {
            $query->where('teacher_id', $request->user()->teacher->id);
        } elseif ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('rombel_id')) {
            $query->where('rombel_id', $request->rombel_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('topic', 'like', "%{$search}%")
                  ->orWhere('activity_summary', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->get('paginate') === 'false' || $request->get('all') === 'true') {
            return response()->json([
                'success' => true,
                'data' => $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get()
            ]);
        }

        $items = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rombel_id'         => 'required|exists:classes,id',
            'subject_id'        => 'required|exists:subjects,id',
            'meet_id'           => 'nullable|exists:meets,id',
            'date'              => 'required|date',
            'session_time'      => 'required|string|max:100',
            'topic'             => 'required|string|max:255',
            'activity_summary'  => 'required|string',
            'notes'             => 'nullable|string',
            'attendance_stats'  => 'nullable|array',
            'photo'             => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // max 10MB
        ]);

        $teacherId = null;
        if ($request->user() && $request->user()->teacher) {
            $teacherId = $request->user()->teacher->id;
        }

        if (!$teacherId) {
            $firstTeacher = \App\Models\Teacher::first();
            $teacherId = $firstTeacher ? $firstTeacher->id : 1;
        }

        $validated['teacher_id'] = $teacherId;
        $validated['approval_status'] = 'pending';

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('journals', 'public');
            $validated['photo_evidence'] = $path;
        }

        $journal = TeacherJournal::create($validated);
        $journal->load(['teacher', 'rombel.className', 'subject', 'meet']);

        return response()->json([
            'success' => true,
            'message' => 'Jurnal mengajar harian berhasil disimpan dan dikirim untuk verifikasi.',
            'data'    => $journal
        ], 201);
    }

    public function show($id)
    {
        $journal = TeacherJournal::with(['teacher', 'rombel.className', 'subject', 'meet', 'verifier'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $journal
        ]);
    }

    public function update(Request $request, $id)
    {
        $journal = TeacherJournal::findOrFail($id);

        // Jika status sudah approved, hanya admin yang boleh mengedit
        if ($journal->approval_status === 'approved' && (!$request->user() || !$request->user()->isAdmin())) {
            return response()->json([
                'success' => false,
                'message' => 'Jurnal yang telah disetujui tidak dapat diedit kembali.'
            ], 403);
        }

        $validated = $request->validate([
            'rombel_id'         => 'required|exists:classes,id',
            'subject_id'        => 'required|exists:subjects,id',
            'meet_id'           => 'nullable|exists:meets,id',
            'date'              => 'required|date',
            'session_time'      => 'required|string|max:100',
            'topic'             => 'required|string|max:255',
            'activity_summary'  => 'required|string',
            'notes'             => 'nullable|string',
            'attendance_stats'  => 'nullable|array',
            'photo'             => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
        ]);

        if ($request->hasFile('photo')) {
            if ($journal->photo_evidence && Storage::disk('public')->exists($journal->photo_evidence)) {
                Storage::disk('public')->delete($journal->photo_evidence);
            }
            $path = $request->file('photo')->store('journals', 'public');
            $validated['photo_evidence'] = $path;
        }

        // Reset status to pending jika diedit
        $validated['approval_status'] = 'pending';
        $journal->update($validated);
        $journal->load(['teacher', 'rombel.className', 'subject', 'meet']);

        return response()->json([
            'success' => true,
            'message' => 'Jurnal mengajar berhasil diperbarui.',
            'data'    => $journal
        ]);
    }

    public function destroy($id)
    {
        $journal = TeacherJournal::findOrFail($id);

        if ($journal->photo_evidence && Storage::disk('public')->exists($journal->photo_evidence)) {
            Storage::disk('public')->delete($journal->photo_evidence);
        }

        $journal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jurnal mengajar berhasil dihapus.'
        ]);
    }

    /**
     * Verifikasi Jurnal Mengajar oleh Waka Kurikulum / Admin
     */
    public function verify(Request $request, $id)
    {
        $journal = TeacherJournal::findOrFail($id);

        $validated = $request->validate([
            'approval_status' => 'required|in:approved,rejected',
            'approval_notes'  => 'nullable|string',
        ]);

        $journal->update([
            'approval_status' => $validated['approval_status'],
            'approval_notes'  => $validated['approval_notes'] ?? null,
            'verified_by'     => $request->user() ? $request->user()->id : null,
            'verified_at'     => now(),
        ]);

        $journal->load(['teacher', 'rombel.className', 'subject', 'verifier']);

        return response()->json([
            'success' => true,
            'message' => 'Status verifikasi jurnal berhasil diperbarui.',
            'data'    => $journal
        ]);
    }
}
