<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionAttendance extends Model
{
    use HasFactory;

    protected $table = 'session_attendances';

    protected $fillable = [
        'session_id',
        'student_id',
        'status',
        'method',
        'notes',
        'checkin_at',
    ];

    protected $casts = [
        'checkin_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(KbmSession::class, 'session_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
