<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = ['meet_id', 'title', 'instructions', 'quiz_type', 'start_time', 'due_date', 'max_score', 'duration_minutes'];

    public function meet()
    {
        return $this->belongsTo(Meet::class);
    }

    public function submissions()
    {
        return $this->hasMany(QuizSubmission::class);
    }
}