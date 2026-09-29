<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = ['meet_id', 'title', 'type', 'content', 'file_path', 'external_url'];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        if ($this->file_path) {
            if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
                return $this->file_path;
            }
            return asset('storage/' . ltrim($this->file_path, '/'));
        }
        return $this->external_url;
    }

    public function meet()
    {
        return $this->belongsTo(Meet::class);
    }
}