<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'meet_id',
        'title',
        'instructions',
        'quiz_type',
        'start_time',
        'end_time',
        'due_date',
        'max_score',
        'duration_minutes',
        'is_random_question',
        'is_random_option',
        'question_bank_id',
        'questions_data',
    ];

    protected $casts = [
        'is_random_question' => 'boolean',
        'is_random_option'   => 'boolean',
        'questions_data'     => 'array',
        'start_time'         => 'datetime',
        'end_time'           => 'datetime',
        'due_date'           => 'datetime',
    ];

    public function meet()
    {
        return $this->belongsTo(Meet::class);
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class);
    }

    public function submissions()
    {
        return $this->hasMany(QuizSubmission::class);
    }
}