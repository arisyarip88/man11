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
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

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
});