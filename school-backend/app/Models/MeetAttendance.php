<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'meet_id',
        'student_id',
        'status',
        'notes',
        'recorded_by',
    ];

    public function meet()
    {
        return $this->belongsTo(Meet::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder()
    {
        return $this->belongsTo(Teacher::class, 'recorded_by');
    }
}
