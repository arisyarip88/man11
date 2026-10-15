<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionBank extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'grade_level',
        'difficulty',
        'title',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    protected $appends = ['difficulty_summary'];

    public function getDifficultySummaryAttribute(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get(['id', 'difficulty']);
        $total = $items->count();

        $easy = $items->where('difficulty', 'easy')->count();
        $medium = $items->where('difficulty', 'medium')->count();
        $hard = $items->where('difficulty', 'hard')->count();

        return [
            'total' => $total,
            'easy' => $easy,
            'medium' => $medium,
            'hard' => $hard,
            'easy_pct' => $total > 0 ? round(($easy / $total) * 100) : 0,
            'medium_pct' => $total > 0 ? round(($medium / $total) * 100) : 0,
            'hard_pct' => $total > 0 ? round(($hard / $total) * 100) : 0,
        ];
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function items()
    {
        return $this->hasMany(QuestionBankItem::class, 'question_bank_id');
    }
}
