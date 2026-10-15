<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KbmSession extends Model
{
    use HasFactory;

    protected $table = 'kbm_sessions';

    protected $fillable = [
        'course_id',
        'schedule_id',
        'teacher_id',
        'date',
        'start_time',
        'end_time',
        'active_meet_id',
        'status',
        'qr_token',
        'allow_self_checkin',
        'notes',
    ];

    protected $casts = [
        'date'               => 'date:Y-m-d',
        'allow_self_checkin' => 'boolean',
    ];

    protected $appends = ['attendance_stats'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function activeMeet()
    {
        return $this->belongsTo(Meet::class, 'active_meet_id');
    }

    public function attendances()
    {
        return $this->hasMany(SessionAttendance::class, 'session_id');
    }

    /**
     * Helper accessor for quick attendance statistics
     */
    public function getAttendanceStatsAttribute()
    {
        $totalStudents = $this->course?->class?->students()?->count() ?? 0;
        
        $counts = [
            'total'      => $totalStudents,
            'H'          => 0,
            'S'          => 0,
            'I'          => 0,
            'A'          => 0,
            'unrecorded' => 0,
        ];

        if ($this->relationLoaded('attendances')) {
            foreach ($this->attendances as $att) {
                if (isset($counts[$att->status])) {
                    $counts[$att->status]++;
                }
            }
            $recordedCount = $counts['H'] + $counts['S'] + $counts['I'] + $counts['A'];
            $counts['unrecorded'] = max(0, $totalStudents - $recordedCount);
        }

        return $counts;
    }
}
