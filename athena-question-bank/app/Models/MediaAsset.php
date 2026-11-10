<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaAsset extends Model
{
    use HasUuids;

    protected $connection = 'question_bank';
    protected $table = 'media_assets';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'id',
        'source_document_id',
        'storage_path',
        'page_number',
        'width',
        'height',
        'mime_type',
        'sha256',
        'stimulus_id',
        'question_id',
        'association_status',
        'associated_at',
        'visual_transcription',
        'visual_description',
        'confidence',
        'warnings',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'page_number'   => 'integer',
        'width'         => 'integer',
        'height'        => 'integer',
        'confidence'    => 'float',
        'associated_at' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class, 'source_document_id');
    }

    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(Stimulus::class, 'stimulus_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}

