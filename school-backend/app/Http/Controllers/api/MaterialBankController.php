<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaterialBankController extends Controller
{
    public function index(Request $request)
    {
        $query = MaterialBank::with(['subject', 'teacher']);

        $teacherId = null;
        if ($request->user() && $request->user()->teacher) {
            $teacherId = $request->user()->teacher->id;
        }

        // Scope filter: 'my' (hanya milik guru), 'public' (pusat/madrasah), atau default (milik sendiri + publik)
        $scope = $request->get('scope', 'all');
        if ($scope === 'my' && $teacherId) {
            $query->where('teacher_id', $teacherId);
        } elseif ($scope === 'public') {
            $query->where('is_public', true);
        } elseif ($teacherId) {
            $query->where(function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId)
                  ->orWhere('is_public', true);
            });
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content_text', 'like', "%{$search}%");
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
            'grade_level'  => 'required|string|in:10,11,12',
            'title'        => 'required|string|max:255',
            'type'         => 'required|string|in:pdf,video,document,link,text',
            'external_url' => 'nullable|string',
            'content_text' => 'nullable|string',
            'is_public'    => 'nullable|boolean',
            'file'         => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,mp4|max:51200', // max 50MB
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

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('materials', 'public');
            $validated['file_path'] = $path;
        }

        $material = MaterialBank::create($validated);
        $material->load(['subject', 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Bahan ajar berhasil disimpan ke Bank Materi',
            'data'    => $material
        ], 201);
    }

    public function show($id)
    {
        $material = MaterialBank::with(['subject', 'teacher'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $material
        ]);
    }

    public function update(Request $request, $id)
    {
        $material = MaterialBank::findOrFail($id);

        $validated = $request->validate([
            'subject_id'   => 'required|exists:subjects,id',
            'grade_level'  => 'required|string|in:10,11,12',
            'title'        => 'required|string|max:255',
            'type'         => 'required|string|in:pdf,video,document,link,text',
            'external_url' => 'nullable|string',
            'content_text' => 'nullable|string',
            'is_public'    => 'nullable|boolean',
            'file'         => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,mp4|max:51200',
        ]);

        if ($request->hasFile('file')) {
            if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
                Storage::disk('public')->delete($material->file_path);
            }
            $path = $request->file('file')->store('materials', 'public');
            $validated['file_path'] = $path;
        }

        $material->update($validated);
        $material->load(['subject', 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Bahan ajar berhasil diperbarui',
            'data'    => $material
        ]);
    }

    public function destroy($id)
    {
        $material = MaterialBank::findOrFail($id);

        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bahan ajar berhasil dihapus dari Bank Materi'
        ]);
    }
}
