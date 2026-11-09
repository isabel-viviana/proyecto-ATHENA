<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $connection = 'question_bank';
    protected $table = 'subjects';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'created_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'subject_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'subject_id');
    }

    public function stimuli(): HasMany
    {
        return $this->hasMany(Stimulus::class, 'subject_id');
    }
}

