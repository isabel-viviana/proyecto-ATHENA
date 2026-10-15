<?php

namespace Database\Factories;

use App\Models\QuestionOption;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'option_letter' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'option_text' => fake()->sentence(8, true),
            'is_correct' => false,
        ];
    }
}
