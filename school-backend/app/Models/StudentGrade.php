<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'score_component_id',
        'student_id',
        'score',
        'feedback',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function scoreComponent()
    {
        return $this->belongsTo(ScoreComponent::class, 'score_component_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
