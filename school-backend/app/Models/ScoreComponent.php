<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'type',
        'code',
        'title',
        'weight',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class, 'score_component_id');
    }
}
