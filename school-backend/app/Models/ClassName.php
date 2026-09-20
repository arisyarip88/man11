<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassName extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'grade_level', 'major'];

    public function classes()
    {
        return $this->hasMany(ClassModel::class, 'class_name_id');
    }
}