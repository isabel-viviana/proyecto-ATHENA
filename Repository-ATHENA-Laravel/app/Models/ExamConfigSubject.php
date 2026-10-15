<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamConfigSubject extends Model
{
    use HasFactory;

    protected $table = 'exam_config_subjects';

    protected $fillable = [
        'exam_config_id',
        'subject_id',
    ];

    public function examConfig(): BelongsTo
    {
        return $this->belongsTo(ExamConfig::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
