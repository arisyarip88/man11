<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'student_id',
        'started_at',
        'submission_text',
        'answers',
        'file_path',
        'submitted_at',
        'score',
        'score_pg',
        'score_essay',
        'is_graded',
        'teacher_feedback',
        'status',
    ];

    protected $casts = [
        'answers'      => 'array',
        'is_graded'    => 'boolean',
        'started_at'   => 'datetime',
        'submitted_at' => 'datetime',
        'score'        => 'decimal:2',
        'score_pg'     => 'decimal:2',
        'score_essay'  => 'decimal:2',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}