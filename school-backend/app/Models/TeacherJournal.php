<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'rombel_id',
        'subject_id',
        'meet_id',
        'date',
        'session_time',
        'topic',
        'activity_summary',
        'notes',
        'attendance_stats',
        'photo_evidence',
        'approval_status',
        'approval_notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'date'             => 'date:Y-m-d',
        'attendance_stats' => 'array',
        'verified_at'      => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function rombel()
    {
        return $this->belongsTo(ClassModel::class, 'rombel_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function meet()
    {
        return $this->belongsTo(Meet::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
