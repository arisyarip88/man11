<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Major;
use Illuminate\Http\Request;

class MajorController extends Controller
{
    public function index(Request $request)
    {
        $query = Major::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        $majors = $query->orderBy('id', 'desc')->paginate(10);

        return response()->json($majors);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20|unique:majors,code',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'required|boolean',
        ]);

        $major = Major::create($validated);

        return response()->json([
            'message' => 'Jurusan berhasil ditambahkan',
            'data'    => $major
        ], 201);
    }

    public function show(Major $major)
    {
        return response()->json($major);
    }

    public function update(Request $request, Major $major)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20|unique:majors,code,' . $major->id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active'   => 'required|boolean',
        ]);

        $major->update($validated);

        return response()->json([
            'message' => 'Jurusan berhasil diperbarui',
            'data'    => $major
        ]);
    }

    public function destroy(Major $major)
    {
        $major->delete();

        return response()->json([
            'message' => 'Jurusan berhasil dihapus'
        ]);
    }
}