<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class ClassModelController extends Controller
{
    public function index(Request $request)
    {
        $query = ClassModel::with(['semester', 'className', 'homeroomTeacher'])
            ->withCount('students');

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('class_name_id')) {
            $query->where('class_name_id', $request->class_name_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('className', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                })->orWhereHas('homeroomTeacher', function ($tq) use ($search) {
                    $tq->where('name', 'like', "%{$search}%");
                })->orWhere('room_number', 'like', "%{$search}%");
            });
        }

        if ($request->get('paginate') === 'false' || $request->get('all') === 'true') {
            return response()->json($query->get());
        }

        $classes = $query->orderBy('id', 'desc')->paginate(10);

        return response()->json($classes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id'         => 'required|exists:semesters,id',
            'class_name_id'       => 'required|exists:class_names,id',
            'homeroom_teacher_id' => 'nullable|exists:teachers,id',
            'capacity'            => 'required|integer|min:1|max:100',
            'room_number'         => 'nullable|string|max:20',
        ]);

        $exists = ClassModel::where('semester_id', $validated['semester_id'])
            ->where('class_name_id', $validated['class_name_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Rombel untuk kelas dan semester ini sudah terdaftar'
            ], 422);
        }

        $class = ClassModel::create($validated);
        $class->load(['semester', 'className', 'homeroomTeacher']);

        return response()->json([
            'message' => 'Data Rombel berhasil ditambahkan',
            'data'    => $class
        ], 201);
    }

    public function show($id)
    {
        $class = ClassModel::with(['semester', 'className', 'homeroomTeacher', 'students'])
            ->withCount('students')
            ->findOrFail($id);

        return response()->json($class);
    }

    public function update(Request $request, $id)
    {
        $class = ClassModel::findOrFail($id);

        $validated = $request->validate([
            'semester_id'         => 'required|exists:semesters,id',
            'class_name_id'       => 'required|exists:class_names,id',
            'homeroom_teacher_id' => 'nullable|exists:teachers,id',
            'capacity'            => 'required|integer|min:1|max:100',
            'room_number'         => 'nullable|string|max:20',
        ]);

        $exists = ClassModel::where('semester_id', $validated['semester_id'])
            ->where('class_name_id', $validated['class_name_id'])
            ->where('id', '!=', $class->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Rombel untuk kelas dan semester ini sudah terdaftar'
            ], 422);
        }

        $class->update($validated);
        $class->load(['semester', 'className', 'homeroomTeacher']);

        return response()->json([
            'message' => 'Data Rombel berhasil diperbarui',
            'data'    => $class
        ]);
    }

    public function destroy($id)
    {
        $class = ClassModel::findOrFail($id);
        $class->delete();

        return response()->json([
            'message' => 'Data Rombel berhasil dihapus'
        ]);
    }

    public function attachStudents(Request $request, $id)
    {
        $class = ClassModel::findOrFail($id);

        $validated = $request->validate([
            'student_ids'   => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        $class->students()->syncWithoutDetaching($validated['student_ids']);

        return response()->json([
            'message' => 'Siswa berhasil ditambahkan ke Rombel ini',
            'data'    => $class->load('students')
        ]);
    }

    public function detachStudent($id, $studentId)
    {
        $class = ClassModel::findOrFail($id);
        $class->students()->detach($studentId);

        return response()->json([
            'message' => 'Siswa berhasil dikeluarkan dari Rombel ini'
        ]);
    }
}
