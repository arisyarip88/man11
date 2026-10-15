<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    public function index(Request $request)
    {
        $query = Discussion::whereNull('parent_id')
            ->with(['student', 'teacher', 'replies.student', 'replies.teacher'])
            ->withCount('replies');

        if ($request->filled('meet_id')) {
            $query->where('meet_id', $request->meet_id);
        }

        $perPage = $request->integer('per_page', 20);
        if ($request->boolean('paginate', false)) {
            return response()->json(
                $query->orderBy('created_at', 'desc')->paginate($perPage)
            );
        }

        $discussions = $query->orderBy('created_at', 'asc')->get();

        return response()->json($discussions);
    }

    public function show($id)
    {
        $discussion = Discussion::with([
            'student', 'teacher', 'meet.course.subject',
            'replies' => function ($q) {
                $q->with(['student', 'teacher'])->orderBy('created_at', 'asc');
            }
        ])->withCount('replies')->findOrFail($id);

        return response()->json($discussion);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meet_id'   => 'required|exists:meets,id',
            'parent_id' => 'nullable|exists:discussions,id',
            'message'   => 'required|string',
        ]);

        $user = $request->user();
        $student = null;
        $teacher = null;

        if ($user) {
            $student = Student::where('nis', $user->username)->orWhere('email', $user->email)->first();
            $teacher = Teacher::where('nip', $user->username)->orWhere('email', $user->email)->first();
        }

        $discussion = Discussion::create([
            'meet_id'    => $validated['meet_id'],
            'parent_id'  => $validated['parent_id'] ?? null,
            'student_id' => $student ? $student->id : null,
            'teacher_id' => $teacher ? $teacher->id : null,
            'message'    => $validated['message'],
        ]);

        return response()->json([
            'message' => 'Pesan diskusi berhasil dikirim',
            'data'    => $discussion->load(['student', 'teacher'])
        ], 201);
    }

    public function destroy($id)
    {
        $discussion = Discussion::findOrFail($id);
        $discussion->delete();

        return response()->json([
            'message' => 'Pesan diskusi berhasil dihapus'
        ]);
    }
}
