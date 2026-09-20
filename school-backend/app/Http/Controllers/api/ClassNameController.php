<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassName;
use Illuminate\Http\Request;

class ClassNameController extends Controller
{
    public function index(Request $request)
    {
        $query = ClassName::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('major', 'like', "%{$search}%");
            });
        }

        if ($request->filled('grade_level')) {
            $query->where('grade_level', $request->grade_level);
        }

        $classes = $query->orderBy('id', 'desc')->paginate(10);

        return response()->json($classes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:50',
            'grade_level' => 'required|in:X,XI,XII|',
            'major'       => 'nullable|string|max:50',
        ]);

        $class = ClassName::create($validated);

        return response()->json([
            'message' => 'Data kelas berhasil ditambahkan',
            'data'    => $class
        ], 201);
    }

    public function show(ClassName $class)
    {
        return response()->json($class);
    }

    public function update(Request $request, ClassName $class)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:50',
            'grade_level' => 'required|in:X,XI,XII',
            'major'       => 'nullable|string|max:50',
        ]);

        $class->update($validated);

        return response()->json([
            'message' => 'Data kelas berhasil diperbarui',
            'data'    => $class
        ]);
    }

    public function destroy(ClassName $class)
    {
        $class->delete();

        return response()->json([
            'message' => 'Data kelas berhasil dihapus'
        ]);
    }
}