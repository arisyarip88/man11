<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meet extends Model
{
    use HasFactory;

    protected $fillable = ['course_id', 'chapter_id', 'meeting_number', 'title', 'description', 'start_time', 'end_time', 'type', 'meeting_link'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function discussions()
    {
        return $this->hasMany(Discussion::class);
    }

    public function attendances()
    {
        return $this->hasMany(MeetAttendance::class);
    }

    public function kbmSessions()
    {
        return $this->hasMany(KbmSession::class, 'active_meet_id');
    }
}