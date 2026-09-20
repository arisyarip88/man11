<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';
    protected $fillable = ['semester_id', 'class_name_id', 'homeroom_teacher_id', 'capacity', 'room_number'];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function className()
    {
        return $this->belongsTo(ClassName::class, 'class_name_id');
    }

    public function homeroomTeacher()
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_student', 'class_id', 'student_id')->withTimestamps();
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'class_id');
    }
}
