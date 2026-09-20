<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\api\UserController;
use App\Http\Controllers\Api\SemesterController;
use App\Http\Controllers\api\RoleController;
use App\http\Controllers\Api\MajorController;
use App\Http\Controllers\Api\ClassNameController;
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
});