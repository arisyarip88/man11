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

    public function isAdmin(): bool
    {
        return (int) $this->role_id === 1 || in_array(strtolower($this->role?->name ?? ''), ['admin', 'super_admin']);
    }

    public function isTeacher(): bool
    {
        return (int) $this->role_id === 2 || in_array(strtolower($this->role?->name ?? ''), ['teacher', 'guru']);
    }

    public function isStudent(): bool
    {
        return (int) $this->role_id === 3 || in_array(strtolower($this->role?->name ?? ''), ['student', 'siswa']);
    }

    public function hasRole(...$roles): bool
    {
        $roleName = strtolower($this->role?->name ?? '');
        $roleId = (int) $this->role_id;

        foreach ($roles as $role) {
            $r = strtolower(trim((string) $role));
            if ($r === 'admin' && ($roleId === 1 || in_array($roleName, ['admin', 'super_admin']))) return true;
            if ($r === 'teacher' && ($roleId === 2 || in_array($roleName, ['teacher', 'guru']))) return true;
            if ($r === 'student' && ($roleId === 3 || in_array($roleName, ['student', 'siswa']))) return true;
            if ($roleName === $r) return true;
        }

        return false;
    }
}