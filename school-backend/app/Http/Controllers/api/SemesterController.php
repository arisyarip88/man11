<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function index(Request $request)
    {
        $query = Semester::query();

        if ($request->has('search') && $request->search != '') {
            $query->where('academic_year', 'like', '%' . $request->search . '%');
        }

        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        $semesters = $query->orderBy('id', 'desc')->paginate(10);

        return response()->json($semesters);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string|max:20',
            'semester_type' => 'required|in:Ganjil,Genap',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'is_active'     => 'required|boolean',
        ]);

        if ($validated['is_active']) {
            Semester::where('is_active', 1)->update(['is_active' => 0]);
        }

        $semester = Semester::create($validated);

        return response()->json([
            'message' => 'Data semester berhasil ditambahkan',
            'data'    => $semester
        ], 201);
    }

    public function show(Semester $semester)
    {
        return response()->json($semester);
    }

    public function update(Request $request, Semester $semester)
    {
        $validated = $request->validate([
            'academic_year' => 'required|string|max:20',
            'semester_type' => 'required|in:Ganjil,Genap',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'is_active'     => 'required|boolean',
        ]);

        if ($validated['is_active']) {
            Semester::where('id', '!=', $semester->id)->update(['is_active' => 0]);
        }

        $semester->update($validated);

        return response()->json([
            'message' => 'Data semester berhasil diperbarui',
            'data'    => $semester
        ]);
    }

    public function destroy(Semester $semester)
    {
        $semester->delete();

        return response()->json([
            'message' => 'Data semester berhasil dihapus'
        ]);
    }
}