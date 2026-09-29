<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SemesterController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\MajorController;
use App\Http\Controllers\Api\ClassNameController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\ClassModelController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\MeetController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\QuizSubmissionController;
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\LearningObjectiveController;
use App\Http\Controllers\Api\MasterCourseController;
use App\Http\Controllers\Api\MaterialBankController;
use App\Http\Controllers\Api\QuestionBankController;
use App\Http\Controllers\Api\CbtController;
use App\Http\Controllers\Api\GradebookController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\TeacherJournalController;
use App\Http\Controllers\Api\SupervisionController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::apiResource('students', StudentController::class);
    Route::apiResource('teachers', TeacherController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::post('/users/enrol', [UserController::class, 'enrolBatch']);

    Route::apiResource('semesters', SemesterController::class);
    Route::apiResource('majors', MajorController::class);
    Route::apiResource('classes', ClassNameController::class);

    // Subject, Rombel, Course, Meet, Material, Quiz, Submission, Discussion, Schedule, Score
    Route::apiResource('subjects', SubjectController::class);
    Route::apiResource('rombel', ClassModelController::class);
    Route::post('/rombel/{id}/students', [ClassModelController::class, 'attachStudents']);
    Route::delete('/rombel/{id}/students/{studentId}', [ClassModelController::class, 'detachStudent']);

    Route::apiResource('courses', CourseController::class);
    Route::apiResource('meets', MeetController::class);
    Route::apiResource('materials', MaterialController::class);

    Route::apiResource('quizzes', QuizController::class);
    Route::post('/quizzes/submit', [QuizSubmissionController::class, 'submit']);
    Route::put('/submissions/{id}/grade', [QuizSubmissionController::class, 'grade']);
    Route::apiResource('submissions', QuizSubmissionController::class)->only(['index']);

    Route::apiResource('discussions', DiscussionController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('schedules', ScheduleController::class);

    Route::get('/scores', [ScoreController::class, 'index']);
    Route::get('/scores/student/{id}', [ScoreController::class, 'studentScores']);

    // Phase 1: Silabus (Learning Objectives) & Master Courses
    Route::apiResource('curriculum/objectives', LearningObjectiveController::class);
    Route::apiResource('master-courses', MasterCourseController::class);
    Route::post('/master-courses/{id}/sync-rombels', [MasterCourseController::class, 'syncRombels']);

    // Phase 2: Repository Bahan Ajar & Bank Soal
    Route::apiResource('material-banks', MaterialBankController::class);
    Route::apiResource('question-banks', QuestionBankController::class);
    Route::get('/question-banks/{id}/items', [QuestionBankController::class, 'getItems']);
    Route::post('/question-banks/{id}/items', [QuestionBankController::class, 'storeItem']);
    Route::put('/question-banks/items/{itemId}', [QuestionBankController::class, 'updateItem']);
    Route::delete('/question-banks/items/{itemId}', [QuestionBankController::class, 'destroyItem']);

    // Phase 3 & 7: Presensi Pertemuan Siswa & Jurnal Harian Guru
    Route::get('/meets/{meetId}/attendances', [AttendanceController::class, 'getMeetAttendances']);
    Route::post('/meets/{meetId}/attendances/batch', [AttendanceController::class, 'batchSaveMeetAttendances']);
    Route::get('/meets/{meetId}/my-attendance', [AttendanceController::class, 'getMyAttendance']);
    Route::post('/meets/{meetId}/attendances/check-in', [AttendanceController::class, 'selfCheckIn']);
    Route::get('/student/attendance-summary', [AttendanceController::class, 'getStudentAttendanceSummary']);
    Route::apiResource('teacher-journals', TeacherJournalController::class);
    Route::patch('/teacher-journals/{id}/verify', [TeacherJournalController::class, 'verify']);

    // Phase 4: CBT Asesmen & Buku Nilai Kurikulum Merdeka
    Route::post('/quizzes/{id}/import-bank-items', [CbtController::class, 'importBankItems']);
    Route::post('/quizzes/{id}/cbt/start', [CbtController::class, 'startExam']);
    Route::post('/quizzes/{id}/cbt/save-progress', [CbtController::class, 'saveProgress']);
    Route::post('/quizzes/{id}/cbt/finish', [CbtController::class, 'finishExam']);
    Route::put('/submissions/{id}/grade-cbt', [CbtController::class, 'gradeCbtSubmission']);

    Route::get('/courses/{courseId}/gradebook', [GradebookController::class, 'getGradebook']);
    Route::post('/courses/{courseId}/gradebook/batch-save', [GradebookController::class, 'batchSaveGrades']);
    Route::post('/courses/{courseId}/score-components', [GradebookController::class, 'storeComponent']);
    Route::delete('/score-components/{id}', [GradebookController::class, 'destroyComponent']);

    // Phase 6: Supervisi Wali Kelas & Ledger Nilai
    Route::get('/supervision/classes', [SupervisionController::class, 'classes']);
    Route::get('/supervision/attendance/{rombelId}', [SupervisionController::class, 'attendance']);
    Route::get('/supervision/ledger/{rombelId}', [SupervisionController::class, 'ledger']);
    Route::get('/supervision/student-analysis/{rombelId}', [SupervisionController::class, 'studentAnalysis']);
});