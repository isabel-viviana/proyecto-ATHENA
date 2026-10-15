<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AttemptAnswer;

class QuestionOption extends Model
{
    use HasFactory, SoftDeletes;

    public const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

    protected $fillable = [
        'question_id',
        'option_letter',
        'option_text',
        'option_image_url',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'option_letter' => 'string',
        'option_image_url' => 'string',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function attemptAnswers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'selected_option_id');
    }
}
