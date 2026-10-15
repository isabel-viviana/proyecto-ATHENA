<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'topic_id' => Topic::factory(),
            'statement' => fake()->sentence(14, true),
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'explanation' => fake()->paragraph(2),
            'source' => fake()->optional()->sentence(3, true),
        ];
    }
}
