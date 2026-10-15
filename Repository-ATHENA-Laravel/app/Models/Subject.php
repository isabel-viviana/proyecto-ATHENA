<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ExamConfig;
use App\Models\Question;
use App\Models\Stimulus;
use App\Models\Topic;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function stimuli(): HasMany
    {
        return $this->hasMany(Stimulus::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function examConfigs(): BelongsToMany
    {
        return $this->belongsToMany(ExamConfig::class, 'exam_config_subjects');
    }
}
