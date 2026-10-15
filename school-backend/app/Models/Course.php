<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['class_id', 'subject_id', 'teacher_id', 'credit_hours'];

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function meets()
    {
        return $this->hasMany(Meet::class);
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class)->orderBy('order', 'asc');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function kbmSessions()
    {
        return $this->hasMany(KbmSession::class);
    }
}