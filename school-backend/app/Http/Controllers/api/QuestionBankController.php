<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuestionBank;
use App\Models\QuestionBankItem;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    public function index(Request $request)
    {
        $query = QuestionBank::with(['subject', 'teacher'])->withCount('items');

        $teacherId = null;
        if ($request->user() && $request->user()->teacher) {
            $teacherId = $request->user()->teacher->id;
        }

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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
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
            'description'  => 'nullable|string',
            'is_public'    => 'nullable|boolean',
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

        $questionBank = QuestionBank::create($validated);
        $questionBank->load(['subject', 'teacher'])->loadCount('items');

        return response()->json([
            'success' => true,
            'message' => 'Paket Bank Soal berhasil dibuat',
            'data'    => $questionBank
        ], 201);
    }

    public function show($id)
    {
        $questionBank = QuestionBank::with(['subject', 'teacher', 'items'])->withCount('items')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $questionBank
        ]);
    }

    public function update(Request $request, $id)
    {
        $questionBank = QuestionBank::findOrFail($id);

        $validated = $request->validate([
            'subject_id'   => 'required|exists:subjects,id',
            'grade_level'  => 'required|string|in:10,11,12',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'is_public'    => 'nullable|boolean',
        ]);

        $questionBank->update($validated);
        $questionBank->load(['subject', 'teacher'])->loadCount('items');

        return response()->json([
            'success' => true,
            'message' => 'Paket Bank Soal berhasil diperbarui',
            'data'    => $questionBank
        ]);
    }

    public function destroy($id)
    {
        $questionBank = QuestionBank::findOrFail($id);
        $questionBank->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paket Bank Soal berhasil dihapus'
        ]);
    }

    // ==========================================
    // BUTIR SOAL (QUESTION BANK ITEMS)
    // ==========================================

    public function getItems($bankId)
    {
        $questionBank = QuestionBank::findOrFail($bankId);
        $items = $questionBank->items()->orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $items
        ]);
    }

    public function storeItem(Request $request, $bankId)
    {
        $questionBank = QuestionBank::findOrFail($bankId);

        $validated = $request->validate([
            'type'           => 'required|in:pg,pg_kompleks,esai,isian',
            'question'       => 'required|string',
            'options'        => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'explanation'    => 'nullable|string',
            'difficulty'     => 'nullable|in:easy,medium,hard',
            'weight'         => 'nullable|numeric|min:0.1',
        ]);

        $validated['difficulty'] = $validated['difficulty'] ?? 'medium';
        $validated['weight'] = $validated['weight'] ?? 1.0;

        $item = $questionBank->items()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil ditambahkan ke bank soal',
            'data'    => $item
        ], 201);
    }

    public function updateItem(Request $request, $itemId)
    {
        $item = QuestionBankItem::findOrFail($itemId);

        $validated = $request->validate([
            'type'           => 'required|in:pg,pg_kompleks,esai,isian',
            'question'       => 'required|string',
            'options'        => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'explanation'    => 'nullable|string',
            'difficulty'     => 'nullable|in:easy,medium,hard',
            'weight'         => 'nullable|numeric|min:0.1',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil diperbarui',
            'data'    => $item
        ]);
    }

    public function destroyItem($itemId)
    {
        $item = QuestionBankItem::findOrFail($itemId);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil dihapus dari bank soal'
        ]);
    }
}
