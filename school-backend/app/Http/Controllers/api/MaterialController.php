<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $query = Material::with('meet');

        if ($request->filled('meet_id')) {
            $query->where('meet_id', $request->meet_id);
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meet_id'      => 'required|exists:meets,id',
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:file,video,link,text',
            'content'      => 'nullable|string',
            'external_url' => 'nullable|url|max:500',
            'file'         => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,zip,rar,mp4,jpg,png|max:20480',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('materials', 'public');
        }

        $material = Material::create([
            'meet_id'      => $validated['meet_id'],
            'title'        => $validated['title'],
            'type'         => $validated['type'],
            'content'      => $validated['content'] ?? null,
            'external_url' => $validated['external_url'] ?? null,
            'file_path'    => $filePath,
        ]);

        return response()->json([
            'message' => 'Materi berhasil ditambahkan',
            'data'    => $material
        ], 201);
    }

    public function show($id)
    {
        $material = Material::with([
            'meet.course.subject',
            'meet.course.class.className',
            'meet.course.teacher',
            'meet.materials',
            'meet.quizzes',
            'meet.discussions'
        ])->findOrFail($id);
        return response()->json($material);
    }

    public function update(Request $request, $id)
    {
        $material = Material::findOrFail($id);

        $validated = $request->validate([
            'meet_id'      => 'required|exists:meets,id',
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:file,video,link,text',
            'content'      => 'nullable|string',
            'external_url' => 'nullable|url|max:500',
            'file'         => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,zip,rar,mp4,jpg,png|max:20480',
        ]);

        if ($request->hasFile('file')) {
            if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
                Storage::disk('public')->delete($material->file_path);
            }
            $material->file_path = $request->file('file')->store('materials', 'public');
        }

        $material->update([
            'meet_id'      => $validated['meet_id'],
            'title'        => $validated['title'],
            'type'         => $validated['type'],
            'content'      => $validated['content'] ?? null,
            'external_url' => $validated['external_url'] ?? null,
            'file_path'    => $material->file_path,
        ]);

        return response()->json([
            'message' => 'Materi berhasil diperbarui',
            'data'    => $material
        ]);
    }

    public function destroy($id)
    {
        $material = Material::findOrFail($id);

        if ($material->file_path && Storage::disk('public')->exists($material->file_path)) {
            // A3: Hanya hapus file fisik jika tidak ada Material lain yang mereferensikannya
            $otherReferences = Material::where('id', '!=', $material->id)
                ->where('file_path', $material->file_path)
                ->exists();

            if (! $otherReferences) {
                Storage::disk('public')->delete($material->file_path);
            }
        }

        $material->delete();

        return response()->json([
            'message' => 'Materi berhasil dihapus'
        ]);
    }
}
