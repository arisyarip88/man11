<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['class.className', 'class.semester', 'subject', 'teacher'])
            ->withCount('meets');

        if ($request->boolean('with_meets') || $request->boolean('with_details')) {
            $query->with(['meets.materials', 'meets.quizzes', 'meets.discussions']);
        }

        $user = $request->user();

        // Role-based automatic filtering if requested or by default for student/teacher
        if ($request->filled('my_courses') && $user) {
            $userRole = $user->role ? strtolower($user->role->name) : '';

            if (in_array($userRole, ['teacher', 'guru'])) {
                $teacher = Teacher::where('nip', $user->username)->orWhere('email', $user->email)->first();
                if ($teacher) {
                    $query->where('teacher_id', $teacher->id);
                } else {
                    return response()->json([]);
                }
            } elseif (in_array($userRole, ['student', 'siswa'])) {
                $student = Student::where('nis', $user->username)->orWhere('email', $user->email)->first();
                if ($student) {
                    $classIds = $student->classes()->pluck('classes.id')->toArray();
                    $query->whereIn('class_id', $classIds);
                } else {
                    return response()->json([]);
                }
            }
        }

        // Direct filters
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('semester_id')) {
            $semesterId = $request->semester_id;
            $query->whereHas('class', function ($cq) use ($semesterId) {
                $cq->where('semester_id', $semesterId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('subject', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                })->orWhereHas('teacher', function ($tq) use ($search) {
                    $tq->where('name', 'like', "%{$search}%");
                })->orWhereHas('class.className', function ($cnq) use ($search) {
                    $cnq->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->get('paginate') === 'false' || $request->get('all') === 'true') {
            return response()->json($query->orderBy('id', 'desc')->get());
        }

        $courses = $query->orderBy('id', 'desc')->paginate(10);

        return response()->json($courses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id'     => 'required|exists:classes,id',
            'subject_id'   => 'required|exists:subjects,id',
            'teacher_id'   => 'required|exists:teachers,id',
            'credit_hours' => 'required|integer|min:1|max:10',
        ]);

        $exists = Course::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Mata pelajaran ini sudah diplotkan untuk kelas tersebut'
            ], 422);
        }

        $course = Course::create($validated);
        $course->load(['class.className', 'class.semester', 'subject', 'teacher']);

        return response()->json([
            'message' => 'Pengalokasian pengajaran (Course) berhasil ditambahkan',
            'data'    => $course
        ], 201);
    }

    public function show($id)
    {
        $course = Course::with([
            'class.className', 
            'class.semester', 
            'class.students',
            'subject', 
            'teacher', 
            'meets.materials', 
            'meets.quizzes.submissions'
        ])
        ->withCount('meets')
        ->findOrFail($id);

        return response()->json($course);
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'class_id'     => 'required|exists:classes,id',
            'subject_id'   => 'required|exists:subjects,id',
            'teacher_id'   => 'required|exists:teachers,id',
            'credit_hours' => 'required|integer|min:1|max:10',
        ]);

        $exists = Course::where('class_id', $validated['class_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('id', '!=', $course->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Mata pelajaran ini sudah diplotkan untuk kelas tersebut'
            ], 422);
        }

        $course->update($validated);
        $course->load(['class.className', 'class.semester', 'subject', 'teacher']);

        return response()->json([
            'message' => 'Pengalokasian pengajaran berhasil diperbarui',
            'data'    => $course
        ]);
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return response()->json([
            'message' => 'Pengalokasian pengajaran berhasil dihapus'
        ]);
    }

    /**
     * A2: Duplikasi Course ke rombel target beserta seluruh pertemuannya
     */
    public function duplicate(Request $request, $id)
    {
        $sourceCourse = Course::with(['chapters', 'meets.materials', 'meets.quizzes'])->findOrFail($id);
        $user = $request->user();

        // A5: Otorisasi: hanya admin atau guru pemilik course
        if (! $user->isAdmin()) {
            $teacher = $user->teacher;
            if (! $teacher || $sourceCourse->teacher_id !== $teacher->id) {
                return response()->json(['message' => 'Anda tidak memiliki hak akses untuk menduplikat kursus ini.'], 403);
            }
        }

        $validated = $request->validate([
            'class_id'   => 'required|exists:classes,id',
            'teacher_id' => 'nullable|exists:teachers,id',
        ]);

        // A4: Tolak bila kombinasi (kelas target, mapel) sudah ada
        $alreadyExists = Course::where('class_id', $validated['class_id'])
            ->where('subject_id', $sourceCourse->subject_id)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'Mata pelajaran ini sudah ada di kelas/rombel tujuan.',
                'error_code' => 'course_already_exists'
            ], 422);
        }

        $targetTeacherId = $validated['teacher_id'] ?? $sourceCourse->teacher_id;

        $newCourse = \Illuminate\Support\Facades\DB::transaction(function () use ($sourceCourse, $validated, $targetTeacherId) {
            // 1. Buat course baru di rombel tujuan
            $newCourse = Course::create([
                'class_id'     => $validated['class_id'],
                'subject_id'   => $sourceCourse->subject_id,
                'teacher_id'   => $targetTeacherId,
                'credit_hours' => $sourceCourse->credit_hours,
            ]);

            // 2. Iterasi 5 (A5): Salin seluruh Bab (Chapters) dan catat mapping id baru
            $chapterMapping = [];
            foreach ($sourceCourse->chapters as $sourceChapter) {
                $newChapter = \App\Models\Chapter::create([
                    'course_id'   => $newCourse->id,
                    'title'       => $sourceChapter->title,
                    'description' => $sourceChapter->description,
                    'order'       => $sourceChapter->order,
                ]);
                $chapterMapping[$sourceChapter->id] = $newChapter->id;
            }

            // 3. Salin seluruh pertemuan (ordered by meeting_number) beserta pemetaan chapter_id
            foreach ($sourceCourse->meets as $sourceMeet) {
                $newMeet = \App\Models\Meet::create([
                    'course_id'      => $newCourse->id,
                    'chapter_id'     => $sourceMeet->chapter_id ? ($chapterMapping[$sourceMeet->chapter_id] ?? null) : null,
                    'meeting_number' => $sourceMeet->meeting_number,
                    'title'          => $sourceMeet->title,
                    'description'    => $sourceMeet->description,
                    'type'           => $sourceMeet->type,
                    'meeting_link'   => $sourceMeet->meeting_link,
                    'start_time'     => null,
                    'end_time'       => null,
                ]);

                // A3: Salin Materi (referensikan file_path yang sama)
                foreach ($sourceMeet->materials as $mat) {
                    \App\Models\Material::create([
                        'meet_id'      => $newMeet->id,
                        'title'        => $mat->title,
                        'type'         => $mat->type,
                        'content'      => $mat->content,
                        'file_path'    => $mat->file_path,
                        'external_url' => $mat->external_url,
                    ]);
                }

                // Salin Kuis (generate token baru jika ada token, tanpa submission)
                foreach ($sourceMeet->quizzes as $quiz) {
                    $newToken = $quiz->access_token ? strtoupper(\Illuminate\Support\Str::random(6)) : null;

                    \App\Models\Quiz::create([
                        'meet_id'            => $newMeet->id,
                        'title'              => $quiz->title,
                        'instructions'       => $quiz->instructions,
                        'quiz_type'          => $quiz->quiz_type,
                        'start_time'         => null,
                        'end_time'           => null,
                        'due_date'           => null,
                        'max_score'          => $quiz->max_score,
                        'duration_minutes'   => $quiz->duration_minutes,
                        'is_random_question' => $quiz->is_random_question,
                        'is_random_option'   => $quiz->is_random_option,
                        'question_bank_id'   => $quiz->question_bank_id,
                        'questions_data'     => $quiz->questions_data,
                        'access_token'       => $newToken,
                        'token_expires_at'   => null,
                        'available_from'     => null,
                        'available_until'    => null,
                        'max_violations'     => $quiz->max_violations,
                    ]);
                }
            }

            return $newCourse;
        });

        $newCourse->load(['class.className', 'class.semester', 'subject', 'teacher', 'meets.materials', 'meets.quizzes']);

        return response()->json([
            'message' => "Kursus berhasil diduplikasi ke kelas target beserta {$sourceCourse->meets->count()} pertemuan.",
            'data'    => $newCourse
        ], 201);
    }
}
