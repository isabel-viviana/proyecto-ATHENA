<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stimulus extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stimuli';

    public const MEDIA_TYPES = [
        'none',
        'image',
        'chart',
        'table',
        'mixed',
    ];

    protected $fillable = [
        'subject_id',
        'title',
        'instructions',
        'content_text',
        'media_type',
        'media_url',
        'media_caption',
        'source_pdf',
        'source_page',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'source_page' => 'integer',
        'media_type' => 'string',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order_in_group', 'asc');
    }
}
