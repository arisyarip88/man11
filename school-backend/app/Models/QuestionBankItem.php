<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionBankItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_bank_id',
        'type',
        'question',
        'options',
        'data',
        'correct_answer',
        'explanation',
        'difficulty',
        'weight',
    ];

    protected $casts = [
        'options' => 'array',
        'data' => 'array',
        'weight' => 'decimal:2',
    ];

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }
}
