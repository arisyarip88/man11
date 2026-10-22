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
        $query = QuestionBank::with(['subject', 'teacher', 'items:id,question_bank_id,difficulty'])->withCount('items');

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

        // B3: Filter berdasarkan tingkat kesulitan paket soal
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
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
            'difficulty'   => 'nullable|string|in:easy,medium,hard',
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
        $questionBank->load(['subject', 'teacher', 'items'])->loadCount('items');

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
            'difficulty'   => 'nullable|string|in:easy,medium,hard',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'is_public'    => 'nullable|boolean',
        ]);

        $questionBank->update($validated);
        $questionBank->load(['subject', 'teacher', 'items'])->loadCount('items');

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

    /**
     * Validasi butir soal sesuai tipe. Mengembalikan payload siap simpan.
     */
    private function validateItemPayload(Request $request): array
    {
        $validated = $request->validate([
            'type'           => 'required|in:' . implode(',', \App\Services\QuestionScorer::TYPES),
            'question'       => 'required|string',
            'image_url'      => 'nullable|string|max:500',
            'image_meta'     => 'nullable|array',
            'options'        => 'nullable|array',
            'data'           => 'nullable|array',
            'correct_answer' => 'nullable|string',
            'explanation'    => 'nullable|string',
            'difficulty'     => 'nullable|in:easy,medium,hard',
            'weight'         => 'nullable|numeric|min:0.1',
        ]);

        $type = $validated['type'];
        $options = $validated['options'] ?? [];
        $data = $validated['data'] ?? [];
        $errors = [];

        if (in_array($type, ['pg', 'pg_kompleks'])) {
            if (count($options) < 2) {
                $errors['options'] = 'Minimal 2 pilihan jawaban.';
            } else {
                $correct = count(array_filter($options, fn ($o) => !empty($o['is_correct'])));
                if ($type === 'pg' && $correct !== 1) {
                    $errors['options'] = 'Pilihan ganda harus punya tepat 1 kunci benar.';
                } elseif ($type === 'pg_kompleks' && $correct < 1) {
                    $errors['options'] = 'PG kompleks harus punya minimal 1 kunci benar.';
                }
            }
            $data = null;
        } elseif ($type === 'isian') {
            $answers = array_values(array_filter(
                array_map(fn ($a) => trim((string) $a), $data['accepted_answers'] ?? []),
                fn ($a) => $a !== ''
            ));
            $data = ['accepted_answers' => $answers];
            $options = null;
        } elseif ($type === 'menjodohkan') {
            $pairs = [];
            foreach (($data['pairs'] ?? []) as $i => $p) {
                $left = trim((string) ($p['left'] ?? ''));
                $right = trim((string) ($p['right'] ?? ''));
                if ($left !== '' && $right !== '') {
                    $pairs[] = ['id' => (string) ($i + 1), 'left' => $left, 'right' => $right];
                }
            }
            if (count($pairs) < 2) {
                $errors['data'] = 'Menjodohkan butuh minimal 2 pasangan lengkap.';
            }
            $data = ['pairs' => $pairs];
            $options = null;
        } elseif ($type === 'benar_salah') {
            $statements = [];
            foreach (($data['statements'] ?? []) as $i => $s) {
                $text = trim((string) ($s['text'] ?? ''));
                if ($text !== '') {
                    $statements[] = [
                        'id' => (string) ($i + 1),
                        'text' => $text,
                        'answer' => filter_var($s['answer'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ];
                }
            }
            if (count($statements) < 1) {
                $errors['data'] = 'Benar/Salah butuh minimal 1 pernyataan.';
            }
            $data = ['statements' => $statements];
            $options = null;
        } else { // esai
            $data = null;
            $options = null;
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $validated['options'] = $options ?: null;
        $validated['data'] = $data;
        $validated['difficulty'] = $validated['difficulty'] ?? 'medium';
        $validated['weight'] = $validated['weight'] ?? 1.0;

        return $validated;
    }

    public function storeItem(Request $request, $bankId)
    {
        $questionBank = QuestionBank::findOrFail($bankId);

        $validated = $this->validateItemPayload($request);

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

        $validated = $this->validateItemPayload($request);

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
