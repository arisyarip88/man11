<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'nisn',
        'nis',
        'name',
        'gender',
        'email',
        'phone',
        'birth_date',
        'birth_place',
        'address',
        'picture',
        'is_active',
        'status',
    ];

    // Accessor untuk URL foto lengkap dan alias is_active
    protected $appends = ['picture_url', 'is_active'];

    public function getPictureUrlAttribute()
    {
        if ($this->picture) {
            return asset('storage/' . $this->picture);
        }
        return null;
    }

    public function getIsActiveAttribute()
    {
        return $this->attributes['status'] ?? 'active';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'nis', 'username');
    }

    public function classes()
    {
        return $this->belongsToMany(ClassModel::class, 'class_student', 'student_id', 'class_id')->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(QuizSubmission::class, 'student_id');
    }

    public function grades()
    {
        return $this->hasMany(StudentGrade::class, 'student_id');
    }

    public function sessionAttendances()
    {
        return $this->hasMany(SessionAttendance::class, 'student_id');
    }
}