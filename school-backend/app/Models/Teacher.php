<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'nip',
        'nik',
        'name',
        'gender',
        'birth_date',
        'email',
        'phone',
        'address',
        'picture',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['picture_url'];

    public function getPictureUrlAttribute()
    {
        if ($this->picture) {
            return asset('storage/' . $this->picture);
        }
        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'nip', 'username');
    }

    public function homeroomClasses()
    {
        return $this->hasMany(ClassModel::class, 'homeroom_teacher_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }
}