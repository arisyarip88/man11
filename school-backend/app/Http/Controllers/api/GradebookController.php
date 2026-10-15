<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ScoreComponent;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradebookController extends Controller
{
    /**
     * Mengambil matriks buku nilai kelas (Kurikulum Merdeka)
     */
    public function getGradebook($courseId)
    {
        $course = Course::with(['subject', 'class.students', 'class.className'])->findOrFail($courseId);
        
        $components = ScoreComponent::where('course_id', $course->id)
            ->orderBy('id', 'asc')
            ->get();

        // Jika belum ada komponen nilai, buatkan template standar Kurikulum Merdeka
        if ($components->isEmpty()) {
            $defaultTemplates = [
                ['course_id' => $course->id, 'type' => 'formatif', 'code' => 'TP-1', 'title' => 'Tugas & Kuis TP 1', 'weight' => 1.0],
                ['course_id' => $course->id, 'type' => 'formatif', 'code' => 'TP-2', 'title' => 'Tugas & Kuis TP 2', 'weight' => 1.0],
                ['course_id' => $course->id, 'type' => 'sumatif_materi', 'code' => 'STS', 'title' => 'Sumatif Tengah Semester', 'weight' => 1.5],
                ['course_id' => $course->id, 'type' => 'sumatif_akhir', 'code' => 'SAS', 'title' => 'Sumatif Akhir Semester', 'weight' => 2.0],
            ];
            foreach ($defaultTemplates as $tpl) {
                ScoreComponent::create($tpl);
            }
            $components = ScoreComponent::where('course_id', $course->id)->orderBy('id', 'asc')->get();
        }

        $componentIds = $components->pluck('id');
        $rawGrades = StudentGrade::whereIn('score_component_id', $componentIds)->get();

        // Mappings [student_id][component_id] => { score, feedback }
        $gradesMap = [];
        foreach ($rawGrades as $g) {
            $gradesMap[$g->student_id][$g->score_component_id] = [
                'score'    => (float) $g->score,
                'feedback' => $g->feedback,
            ];
        }

        $students = $course->class ? $course->class->students : collect([]);
        $kktp = 75.0; // Standar KKTP Madrasah

        $rows = [];
        $totalWeight = $components->sum('weight');

        foreach ($students as $student) {
            $studentGrades = $gradesMap[$student->id] ?? [];
            $weightedSum = 0;
            $formatifSum = 0;
            $formatifCount = 0;
            $sumatifSum = 0;
            $sumatifCount = 0;

            $evaluatedWeight = 0;
            foreach ($components as $comp) {
                if (isset($studentGrades[$comp->id]) && $studentGrades[$comp->id]['score'] > 0) {
                    $val = (float) $studentGrades[$comp->id]['score'];
                    $weightedSum += ($val * (float) $comp->weight);
                    $evaluatedWeight += (float) $comp->weight;

                    if ($comp->type === 'formatif') {
                        $formatifSum += $val;
                        $formatifCount++;
                    } else {
                        $sumatifSum += $val;
                        $sumatifCount++;
                    }
                }
            }

            $divisor = $evaluatedWeight > 0 ? $evaluatedWeight : $totalWeight;
            $finalScore = $divisor > 0 ? round($weightedSum / $divisor, 2) : 0;
            $avgFormatif = $formatifCount > 0 ? round($formatifSum / $formatifCount, 2) : 0;
            $avgSumatif = $sumatifCount > 0 ? round($sumatifSum / $sumatifCount, 2) : 0;

            $rows[] = [
                'student_id'   => $student->id,
                'nisn'         => $student->nisn,
                'name'         => $student->name,
                'grades'       => $studentGrades,
                'avg_formatif' => $avgFormatif,
                'avg_sumatif'  => $avgSumatif,
                'final_score'  => $finalScore,
                'is_passed'    => $finalScore >= $kktp,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'course'     => [
                    'id'           => $course->id,
                    'subject_name' => $course->subject?->name,
                    'class_name'   => $course->class?->className?->name ?: 'Kelas',
                ],
                'components' => $components,
                'kktp'       => $kktp,
                'rows'       => $rows,
            ]
        ]);
    }

    /**
     * Batch simpan input nilai buku nilai
     */
    public function batchSaveGrades(Request $request, $courseId)
    {
        $validated = $request->validate([
            'grades'                      => 'required|array',
            'grades.*.student_id'         => 'required|exists:students,id',
            'grades.*.score_component_id' => 'required|exists:score_components,id',
            'grades.*.score'              => 'required|numeric|min:0|max:100',
            'grades.*.feedback'           => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['grades'] as $g) {
                StudentGrade::updateOrCreate(
                    [
                        'score_component_id' => $g['score_component_id'],
                        'student_id'         => $g['student_id'],
                    ],
                    [
                        'score'    => $g['score'],
                        'feedback' => $g['feedback'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Nilai berhasil disimpan ke dalam Buku Nilai.'
        ]);
    }

    /**
     * Tambah Komponen Nilai Baru
     */
    public function storeComponent(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        $validated = $request->validate([
            'type'   => 'required|in:formatif,sumatif_materi,sumatif_akhir',
            'code'   => 'required|string|max:50',
            'title'  => 'required|string|max:255',
            'weight' => 'required|numeric|min:0.1',
        ]);

        $validated['course_id'] = $course->id;
        $component = ScoreComponent::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Komponen nilai berhasil ditambahkan.',
            'data'    => $component
        ], 201);
    }

    /**
     * Hapus Komponen Nilai
     */
    public function destroyComponent($componentId)
    {
        $component = ScoreComponent::findOrFail($componentId);
        $component->delete();

        return response()->json([
            'success' => true,
            'message' => 'Komponen nilai berhasil dihapus.'
        ]);
    }
}
