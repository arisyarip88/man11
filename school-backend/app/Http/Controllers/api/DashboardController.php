<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Schedule;
use App\Models\QuizSubmission;
use App\Models\TeacherJournal;
use App\Models\Discussion;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get real-time stats for Admin Dashboard
     */
    public function stats(Request $request)
    {
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $todayName = $dayNames[date('w')];

        $totalStudents = Student::count();
        $totalTeachers = Teacher::count();
        $totalSubjects = Subject::count();
        $totalClasses  = ClassModel::count();
        $totalCourses  = Course::count();
        $todaySchedulesCount = Schedule::where('day', $todayName)->count();

        // Recent real activities
        $activities = [];

        // Recent quiz submissions
        $recentSubmissions = QuizSubmission::with(['student', 'quiz.meet.course.subject'])
            ->latest('submitted_at')
            ->take(5)
            ->get();

        foreach ($recentSubmissions as $sub) {
            $studentName = $sub->student?->name ?: 'Siswa';
            $subjectName = $sub->quiz?->meet?->course?->subject?->name ?: 'Mata Pelajaran';
            $quizTitle   = $sub->quiz?->title ?: 'Tugas/Kuis';
            $timeAgo     = $sub->submitted_at ? $sub->submitted_at->diffForHumans() : 'Baru saja';

            $activities[] = [
                'id'     => 'sub_' . $sub->id,
                'user'   => $studentName,
                'role'   => 'Siswa',
                'action' => "Mengumpulkan {$quizTitle} ({$subjectName})",
                'time'   => $timeAgo,
                'status' => $sub->status === 'graded' ? 'Dinilai' : 'Selesai',
                'created_at' => $sub->submitted_at ?: $sub->created_at,
            ];
        }

        // Recent teacher journals
        $recentJournals = TeacherJournal::with(['teacher', 'subject', 'rombel.className'])
            ->latest()
            ->take(3)
            ->get();

        foreach ($recentJournals as $jnl) {
            $teacherName = $jnl->teacher?->name ?: 'Guru';
            $subjectName = $jnl->subject?->name ?: 'Pelajaran';
            $activities[] = [
                'id'     => 'jnl_' . $jnl->id,
                'user'   => $teacherName,
                'role'   => 'Guru',
                'action' => "Mengisi Jurnal Mengajar {$subjectName}",
                'time'   => $jnl->created_at ? $jnl->created_at->diffForHumans() : 'Hari ini',
                'status' => $jnl->approval_status === 'verified' ? 'Terverifikasi' : 'Menunggu',
                'created_at' => $jnl->created_at,
            ];
        }

        // Sort activities by latest
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });
        $activities = array_slice($activities, 0, 6);

        return response()->json([
            'success' => true,
            'data' => [
                'total_students'  => $totalStudents,
                'total_teachers'  => $totalTeachers,
                'total_subjects'  => $totalSubjects,
                'total_classes'   => $totalClasses,
                'total_courses'   => $totalCourses,
                'today_schedules' => $todaySchedulesCount,
                'recent_activities' => $activities,
            ]
        ]);
    }
}
