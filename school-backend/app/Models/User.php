<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'role_id',
        'username',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relasi ke Role
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    public function student()
    {
        return $this->hasOne(Student::class, 'nis', 'username');
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class, 'nip', 'username');
    }

    public function isAdmin()
    {
        return in_array(strtolower($this->role?->name ?? ''), ['admin', 'super_admin']);
    }
}