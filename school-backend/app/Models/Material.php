<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = ['meet_id', 'title', 'type', 'content', 'file_path', 'external_url'];

    public function meet()
    {
        return $this->belongsTo(Meet::class);
    }
}