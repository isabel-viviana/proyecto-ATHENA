<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stimulus extends Model
{
    use HasUuids;

    protected $connection = 'question_bank';
    protected $table = 'stimuli';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'subject_id',
        'source_document_id',
        'title',
        'content_text',
        'media_type',
        'media_path',
        'source_page',
        'instructions',
        'created_at',
    ];

    protected $casts = [
        'subject_id'  => 'integer',
        'source_page' => 'integer',
        'created_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Stimulus $stimulus) {
            if (empty($stimulus->created_at)) {
                $stimulus->created_at = now();
            }
            if (empty($stimulus->media_type)) {
                $stimulus->media_type = 'none';
            }
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'stimulus_id')->orderBy('order_in_group', 'asc');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class, 'source_document_id');
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'stimulus_id')
            ->orderBy('page_number', 'asc')
            ->orderBy('created_at', 'asc');
    }

    public function confirmedMediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'stimulus_id')
            ->where('association_status', 'confirmed')
            ->orderBy('page_number', 'asc')
            ->orderBy('created_at', 'asc');
    }
}

