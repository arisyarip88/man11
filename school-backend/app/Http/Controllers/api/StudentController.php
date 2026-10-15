<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    // Index (Paginate & Search)
    public function index(Request $request)
    {
        $query = Student::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->latest()->paginate($request->get('per_page', 10));

        return response()->json($students);
    }

    // Store (Tambah Data)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nisn'        => 'required|string|max:10|unique:students,nisn',
            'nis'         => 'nullable|string|max:20|unique:students,nis',
            'name'        => 'required|string|max:255',
            'gender'      => 'required|in:male,female',
            'email'       => 'required|email|max:255|unique:students,email',
            'phone'       => 'nullable|string|max:20',
            'birth_date'  => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',
            'address'     => 'nullable|string',
            'picture'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active'   => 'nullable|in:active,graduated,transferred',
            'status'      => 'nullable|in:active,graduated,transferred',
        ]);

        if (empty($validated['is_active']) && empty($validated['status'])) {
            $validated['status'] = 'active';
        } else {
            $validated['status'] = $validated['status'] ?? $validated['is_active'];
        }
        unset($validated['is_active']);

        if ($request->hasFile('picture')) {
            $validated['picture'] = $request->file('picture')->store('students', 'public');
        }

        $student = Student::create($validated);

        return response()->json([
            'message' => 'Data siswa berhasil ditambahkan',
            'data'    => $student
        ], 201);
    }

    // Show (Detail Data)
    public function show($id)
    {
        $student = Student::findOrFail($id);
        return response()->json(['data' => $student]);
    }

    // Update (Edit Data)
    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);

        $validated = $request->validate([
            'nisn'        => ['required', 'string', 'max:10', Rule::unique('students')->ignore($student->id)],
            'nis'         => ['nullable', 'string', 'max:20', Rule::unique('students')->ignore($student->id)],
            'name'        => 'required|string|max:255',
            'gender'      => 'required|in:male,female',
            'email'       => ['required', 'email', 'max:255', Rule::unique('students')->ignore($student->id)],
            'phone'       => 'nullable|string|max:20',
            'birth_date'  => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',
            'address'     => 'nullable|string',
            'picture'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active'   => 'nullable|in:active,graduated,transferred',
            'status'      => 'nullable|in:active,graduated,transferred',
        ]);

        if (isset($validated['status']) || isset($validated['is_active'])) {
            $validated['status'] = $validated['status'] ?? $validated['is_active'];
        }
        unset($validated['is_active']);

        if ($request->hasFile('picture')) {
            if ($student->picture && Storage::disk('public')->exists($student->picture)) {
                Storage::disk('public')->delete($student->picture);
            }
            $validated['picture'] = $request->file('picture')->store('students', 'public');
        }

        $student->update($validated);

        return response()->json([
            'message' => 'Data siswa berhasil diperbarui',
            'data'    => $student
        ]);
    }

    // Destroy (Hapus Data)
    public function destroy($id)
    {
        $student = Student::findOrFail($id);

        if ($student->picture && Storage::disk('public')->exists($student->picture)) {
            Storage::disk('public')->delete($student->picture);
        }

        $student->delete();

        return response()->json([
            'message' => 'Data siswa berhasil dihapus'
        ]);
    }

    /**
     * A4 / A7: Reset sesi perangkat siswa
     */
    public function resetSession($id)
    {
        $student = Student::findOrFail($id);
        $user = \App\Models\User::where('username', $student->nis)
            ->orWhere('email', $student->email)
            ->first();

        if ($user) {
            $user->tokens()->delete();
            \App\Models\UserSession::where('user_id', $user->id)->update(['is_active' => false]);
        }

        return response()->json([
            'message' => "Sesi perangkat untuk siswa {$student->name} berhasil direset."
        ]);
    }
}