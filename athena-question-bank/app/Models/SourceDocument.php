<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SourceDocument extends Model
{
    use HasUuids;

    /**
     * Conexión a la base de datos de Supabase PostgreSQL.
     *
     * @var string
     */
    protected $connection = 'question_bank';

    /**
     * Nombre de la tabla en Supabase PostgreSQL.
     *
     * @var string
     */
    protected $table = 'source_documents';

    /**
     * Tipo de clave primaria UUID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Clave primaria no autoincremental.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * El esquema de Supabase gestiona created_at pero no contiene updated_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Columnas asignables de forma masiva (ajustadas estrictamente al esquema real).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'title',
        'storage_path',
        'year',
        'exam_type',
        'total_pages',
        'has_answer_key',
        'status',
        'extraction_metadata',
        'created_at',
    ];

    /**
     * Tipos de datos de las columnas.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'year' => 'integer',
        'total_pages' => 'integer',
        'has_answer_key' => 'boolean',
        'extraction_metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Valores predeterminados del modelo al crearse.
     */
    protected static function booted(): void
    {
        static::creating(function (SourceDocument $doc) {
            if (empty($doc->created_at)) {
                $doc->created_at = now();
            }
            if (empty($doc->status)) {
                $doc->status = 'uploaded';
            }
            if (empty($doc->exam_type)) {
                $doc->exam_type = 'Saber 11';
            }
        });
    }

    public function iqpeJobs()
    {
        return $this->hasMany(IqpeDocumentJob::class, 'source_document_id')->orderBy('run_number', 'desc');
    }

    public function latestIqpeJob()
    {
        return $this->hasOne(IqpeDocumentJob::class, 'source_document_id')->latestOfMany('run_number');
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'source_document_id');
    }

    public function stimuli()
    {
        return $this->hasMany(Stimulus::class, 'source_document_id');
    }
}

