<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $query = Teacher::query();

        // Pencarian Nama, NIP, NIK, Email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Perbaikan filter status keaktifan
        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->is_active);
        }

        $teachers = $query->latest()->paginate($request->get('per_page', 10));

        return response()->json($teachers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nip'       => 'required|string|max:20|unique:teachers,nip',
            'nik'       => 'nullable|string|max:16|unique:teachers,nik',
            'name'      => 'required|string|max:255',
            'gender'    => 'required|in:male,female',
            'email'     => 'required|email|max:255|unique:teachers,email',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string',
            'picture'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'required|in:0,1,true,false',
        ]);

        $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('picture')) {
            $validated['picture'] = $request->file('picture')->store('teachers', 'public');
        }

        $teacher = Teacher::create($validated);

        return response()->json([
            'message' => 'Data guru berhasil ditambahkan',
            'data'    => $teacher
        ], 201);
    }

    public function show($id)
    {
        $teacher = Teacher::findOrFail($id);
        return response()->json(['data' => $teacher]);
    }

    public function update(Request $request, $id)
    {
        $teacher = Teacher::findOrFail($id);

        $validated = $request->validate([
            'nip'       => ['required', 'string', 'max:20', Rule::unique('teachers')->ignore($teacher->id)],
            'nik'       => ['nullable', 'string', 'max:16', Rule::unique('teachers')->ignore($teacher->id)],
            'name'      => 'required|string|max:255',
            'gender'    => 'required|in:male,female',
            'email'     => ['required', 'email', 'max:255', Rule::unique('teachers')->ignore($teacher->id)],
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string',
            'picture'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'required|in:0,1,true,false',
        ]);

        $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('picture')) {
            if ($teacher->picture && Storage::disk('public')->exists($teacher->picture)) {
                Storage::disk('public')->delete($teacher->picture);
            }
            $validated['picture'] = $request->file('picture')->store('teachers', 'public');
        } else {
            unset($validated['picture']);
        }

        $teacher->update($validated);

        return response()->json([
            'message' => 'Data guru berhasil diperbarui',
            'data'    => $teacher
        ]);
    }

    public function destroy($id)
    {
        $teacher = Teacher::findOrFail($id);

        if ($teacher->picture && Storage::disk('public')->exists($teacher->picture)) {
            Storage::disk('public')->delete($teacher->picture);
        }

        $teacher->delete();

        return response()->json([
            'message' => 'Data guru berhasil dihapus'
        ]);
    }
}