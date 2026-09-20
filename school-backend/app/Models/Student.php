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
    ];

    // Accessor untuk URL foto lengkap
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
        return $this->belongsTo(User::class, 'nis', 'username');
    }
}