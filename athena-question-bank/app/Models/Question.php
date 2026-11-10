<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Question extends Model
{
    use HasUuids;

    protected $connection = 'question_bank';
    protected $table = 'questions';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'id',
        'root_question_id',
        'version',
        'is_current_version',
        'subject_id',
        'topic_id',
        'stimulus_id',
        'source_document_id',
        'order_in_group',
        'statement',
        'question_image_path',
        'source_page',
        'visual_transcription',
        'visual_description',
        'extraction_warnings',
        'difficulty',
        'explanation',
        'key_status',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'subject_id'         => 'integer',
        'topic_id'           => 'integer',
        'version'            => 'integer',
        'order_in_group'     => 'integer',
        'source_page'        => 'integer',
        'is_current_version' => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Question $q) {
            if (empty($q->root_question_id)) {
                $q->root_question_id = (string) Str::uuid();
            }
            if (empty($q->version)) {
                $q->version = 1;
            }
            if (! isset($q->is_current_version)) {
                $q->is_current_version = true;
            }
            if (empty($q->difficulty)) {
                $q->difficulty = 'medium';
            }
            if (empty($q->key_status)) {
                $q->key_status = 'unverified';
            }
            if (empty($q->status)) {
                $q->status = 'draft';
            }
            if (empty($q->order_in_group)) {
                $q->order_in_group = 1;
            }
        });
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id')->orderBy('option_letter', 'asc');
    }

    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(Stimulus::class, 'stimulus_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class, 'source_document_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'question_id')
            ->orderBy('page_number', 'asc')
            ->orderBy('created_at', 'asc');
    }

    public function confirmedMediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'question_id')
            ->where('association_status', 'confirmed')
            ->orderBy('page_number', 'asc')
            ->orderBy('created_at', 'asc');
    }
}

