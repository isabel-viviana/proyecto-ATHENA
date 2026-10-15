<?php

namespace Database\Factories;

use App\Models\AttemptAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptAnswer>
 */
class AttemptAnswerFactory extends Factory
{
    protected $model = AttemptAnswer::class;

    public function definition(): array
    {
        $question = Question::factory()->create();
        $correctOption = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'option_letter' => 'A',
            'is_correct' => true,
        ]);

        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'question_id' => $question->id,
            'selected_option_id' => $correctOption->id,
            'is_correct' => true,
            'time_spent_seconds' => fake()->numberBetween(5, 90),
            'explanation_viewed' => fake()->boolean(20),
        ];
    }
}
