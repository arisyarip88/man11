<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use Illuminate\Http\Request;

class LearningObjectiveController extends Controller
{
    public function index(Request $request)
    {
        $query = LearningObjective::with(['subject', 'teacher']);

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('phase')) {
            $query->where('phase', $request->phase);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->get('paginate') === 'false' || $request->get('all') === 'true') {
            return response()->json([
                'success' => true,
                'data' => $query->orderBy('grade_level', 'asc')->orderBy('code', 'asc')->get()
            ]);
        }

        $items = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject_id'   => 'required|exists:subjects,id',
            'phase'        => 'required|string|in:E,F',
            'grade_level'  => 'required|string|in:10,11,12',
            'code'         => 'required|string|max:50',
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'target_hours' => 'nullable|integer|min:0',
        ]);

        $teacherId = null;
        if ($request->user() && $request->user()->teacher) {
            $teacherId = $request->user()->teacher->id;
        }

        $validated['teacher_id'] = $teacherId;
        $validated['target_hours'] = $validated['target_hours'] ?? 0;

        $objective = LearningObjective::create($validated);
        $objective->load(['subject', 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Capaian Pembelajaran (CP/TP) berhasil ditambahkan',
            'data'    => $objective
        ], 201);
    }

    public function show($id)
    {
        $objective = LearningObjective::with(['subject', 'teacher'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $objective
        ]);
    }

    public function update(Request $request, $id)
    {
        $objective = LearningObjective::findOrFail($id);

        $validated = $request->validate([
            'subject_id'   => 'required|exists:subjects,id',
            'phase'        => 'required|string|in:E,F',
            'grade_level'  => 'required|string|in:10,11,12',
            'code'         => 'required|string|max:50',
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'target_hours' => 'nullable|integer|min:0',
            'is_active'    => 'nullable|boolean',
        ]);

        $objective->update($validated);
        $objective->load(['subject', 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Capaian Pembelajaran berhasil diperbarui',
            'data'    => $objective
        ]);
    }

    public function destroy($id)
    {
        $objective = LearningObjective::findOrFail($id);
        $objective->delete();

        return response()->json([
            'success' => true,
            'message' => 'Capaian Pembelajaran berhasil dihapus'
        ]);
    }
}
